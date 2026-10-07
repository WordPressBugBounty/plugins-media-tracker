# Media Tracker — Architecture Review & Target Design

> Role: Senior Software Architect review (20 yrs perspective)
> Scope: unused-media scan pipeline, duplicate detection, deletion flow, scalability up to **10M media items**.
> Audience: plugin maintainers / future contributors.

---

## TL;DR (বাংলা)

এই প্লাগইনের মূল সমস্যা কোডের বাগ না — **স্থাপত্য (architecture)**। পুরো স্ক্যান একটা PHP প্রসেসে, সব রেজাল্ট একটা option-এ, আর শনাক্তকরণ পুরোপুরি "full rescan" নির্ভর। ছোট সাইটে (৫–২০ হাজার মিডিয়া) সব ঠিক চলে; ১ লাখ+ এ টাইমআউট শুরু হয়; ১ কোটি (10M) মিডিয়াতে এই ডিজাইন **গাণিতিকভাবেই অসম্ভব** (নিচে হিসাব আছে)। সমাধান: custom DB টেবিল + chunked/resumable job system + event-driven incremental update + safe deletion flow। বিস্তারিত নিচে।

---

## 1. What the plugin does today

```
Admin clicks "Scan Unused Media"  (#run-media-scan)
        │
        ▼
AJAX run_media_scan ──► schedule WP-Cron single event ──► spawn_cron()
        │                                                   │
        │ 500ms polling (get_media_scan_progress)           ▼
        │                                     run_scan_bg() ──► Unused_Media_List::scan_and_save_snapshot()
        │                                                          │
        │                                                          ├─ get_used_media_ids()   ← ~1,000-line monolith
        │                                                          │    ├─ 20 "steps" tracked in per-user transient
        │                                                          │    ├─ bounded loops (steps 3–13, resumable offsets)
        │                                                          │    └─ 11 unbounded while(true) loops
        │                                                          │         (Gutenberg, gallery, Elementor, Divi ×3,
        │                                                          │          raw URLs, excerpt, postmeta, options, widgets)
        │                                                          ├─ array_diff(all attachments, used ids)
        │                                                          └─ file_exists()+filesize() per unused file
        │
        ▼
Snapshot  ──►  option `media_tracker_unused_ids_snapshot`  (ONE option row, ALL unused IDs)
        ▼
UI: fake progress bar (client animates to 99%, waits for server step 20/20)
        ▼
"Remove All" ──► single AJAX request looping wp_delete_attachment(id, force=true)
```

Key files:
- `includes/Admin/Menu.php` — menu + all AJAX handlers + cron runner
- `includes/Admin/Unused_Media_List.php` — scan engine + WP_List_Table + caching
- `includes/Admin/Media_Usage.php` — secondary usage scanner
- `includes/Admin/Duplicate_Images.php` — duplicate detection via `md5_file()`
- `assets/src/js/mt-admin.js` — jQuery admin UI, polling + fake progress

---

## 2. Current architectural flaws (ranked by severity)

### 🔴 Critical — design cannot scale

| # | Flaw | Where | Why it breaks |
|---|------|-------|---------------|
| C1 | **All state in one PHP process** | `Menu.php` `run_scan_bg()`; `set_time_limit(600)` at `Unused_Media_List.php:197` | One cron run must finish the whole scan. Hosts kill long processes (PHP `max_execution_time`, FastCGI/`mod_proxy` timeouts). Dead process = frozen progress, no resume for 11 of the loops. |
| C2 | **Result set stored in a single option row** | `update_option('media_tracker_unused_ids_snapshot', $unused_ids)` | Options are serialized blobs. 10M IDs ≈ 70–100 MB — exceeds `max_allowed_packet`, busts memory, and rewriting it per scan is brutal. Options are simply not a dataset store. |
| C3 | **Unbounded in-memory set operations** | `array_diff()` / `array_unique()` / `array_merge()` in loops (`Unused_Media_List.php:1171`, `1133`, and inside every loop) | PHP arrays cost ~80+ bytes/element with overhead. Multiple copies of a 10M-element set = multiple GB → guaranteed OOM regardless of `wp_raise_memory_limit()`. |
| C4 | **Full-rescan detection model** | entire `get_used_media_ids()` | To find ONE newly-unused image you re-scan EVERYTHING. No incremental invalidation on `save_post` / meta update / attachment delete. At 10M items, full scans are infeasible — the model itself is the bottleneck. |
| C5 | **Deep `OFFSET` pagination on `wp_posts`** | every batch loop: `LIMIT 500 OFFSET n` | `OFFSET 500000` makes MySQL walk half a million rows first. Cost grows linearly with position → total O(n²). Must be keyset pagination (`WHERE ID > cursor ORDER BY ID LIMIT n`). |
| C6 | **Same table scanned ~6 times** | separate `while(true)` passes for `wp-image-`, `wp:image`, `wp:cover/…`, `[gallery]`, Divi, raw URLs | Six full passes of LIKE `'%…%'` over `post_content` (the biggest column in WP) instead of one combined pass. |

### 🟠 High — correctness & safety

| # | Flaw | Where | Risk |
|---|------|-------|------|
| H1 | **False-positive deletions are irreversible** | `Menu.php:564` — `wp_delete_attachment( $id, true )` force-delete | Numeric heuristics ("any number in any postmeta = image ID", ACF walk at `Unused_Media_List.php:304-431`, widget key-patterns) can mislabel. One wrong match → user's file is **permanently gone**. No trash, no undo, no dry-run. |
| H2 | **`unserialize()` without `allowed_classes`** | ACF walk `:330,:415`, widgets `:1062` | If an editor-level user can store a crafted serialized payload in meta/widgets, admin-triggered scan instantiates objects → POP gadget chain. Fix: `unserialize( $v, array( 'allowed_classes' => false ) )`. |
| H3 | **No global scan lock** | cron runner + `run_media_scan_sync` AJAX fallback | Two admins (or cron + sync fallback) can run full scans concurrently, interleaving snapshot writes → corrupted results; doubled DB load. |
| H4 | **"Delete all" in one request** | `handle_remove_all_unused_media()` `Menu.php:545` | Loop of `wp_delete_attachment` over every unused ID in a single AJAX call. 100k files = guaranteed timeout mid-delete, half-deleted state, no progress, no batching. |
| H5 | **Transients used as durable job state** | `media_scan_progress_{user_id}` | Object caches (Redis/Memcached) evict under memory pressure — mid-scan state can silently vanish. Also keyed per-user: admin B can't see admin A's scan; two admins = two parallel full scans (see H3). |
| H6 | **Cache cleared by direct SQL on `_transient_%` rows** | `clear_cache()` `Unused_Media_List.php` (DELETE on options LIKE `_transient_unused_media_%`) | Bypasses the object-cache API: works only when transients live in the DB, silently breaks with Redis/Memcached. Also deletes rows the object cache still holds. |

### 🟡 Medium — quality & maintainability

| # | Flaw | Where |
|---|------|-------|
| M1 | **Monolith method** — `get_used_media_ids()` ≈ 1,000 lines, 20 steps, 11 loops, regex + queries + progress + persistence all interleaved. Untestable. | `Unused_Media_List.php:161-1146` |
| M2 | **Builder plugins hardcoded** — Elementor/Divi/ACF/Woo logic inline; adding a builder = editing the monolith. | same file |
| M3 | **Object cache keyed by `md5(query)` with 300s TTL** (`get_cached_db_result`) — scan spans > 300s can read one table state at step 3 and another at step 15 (inconsistent snapshot); also stampede-prone. | `Unused_Media_List.php:1618` |
| M4 | **Duplicate detection = `md5_file()` on full files** — disk I/O of every file, every scan; no size pre-grouping, no partial-hash short-circuit. At 10M files (say 20 TB) this is days of I/O. | `Duplicate_Images.php:134` |
| M5 | **`filesize()`/`file_exists()` per unused file in PHP** — 10M stat() calls per scan; should be SQL `SUM()` on stored metadata. | `scan_and_save_snapshot()` |
| M6 | **Fake client progress** — UI animates to 99% and waits; hides real stalls, misleads users (the exact bug we debugged). | `mt-admin.js` |
| M7 | **Untranslated hardcoded UI strings inside engine** (`'Scanning featured images...'` etc.) | scan engine |
| M8 | **No automated tests, no CI, mixed tabs/spaces** (~1,800 phpcs baseline violations) | repo-wide |
| M9 | **Scans `wp_options.option_value` with `LIKE '%…%'`** — full table scans over multi-MB blobs (Elementor CSS, widget JSON) → regex over megabyte strings, memory spikes. | options/widget loops |
| M10 | **Cron leftovers** — old `media_tracker_background_scan` / `media_tracker_batch_process` hooks coexist with `media_tracker_run_media_scan_bg`; dead code paths. | cron list |

---

## 3. Feasibility math — why the current design dies at 10M media

Assume: 10M attachments, 10M posts, `post_content` avg 50 KB.

| Operation | Current cost | Verdict |
|---|---|---|
| Hold all attachment IDs | 10M × ~80 B (PHP array) ≈ **800 MB–1 GB per array** | OOM — impossible |
| `array_diff(all, used)` on two such arrays | needs both in RAM → **2+ GB** | OOM — impossible |
| Snapshot option row | 10M ints serialized ≈ **80–100 MB single row** | > `max_allowed_packet` — impossible |
| Save `used_ids` into progress transient every 10 batches | rewrite of the same blob repeatedly | kills DB + cache |
| 6 × LIKE-passes over 10M posts × 50 KB content | ~3 TB of content scanned per pass | days of DB time |
| `OFFSET 5,000,000 LIMIT 500` | walks 5M rows to reach the page | O(n²) total |
| `md5_file()` on 10M files | reads entire library from disk (e.g., 20 TB) | days of I/O |
| `filesize()` loop over ~5M unused files | 5M `stat()` syscalls | minutes-hours, all in one process |

**Conclusion:** this is not a tuning problem. The storage model (option blob), the execution model (one process), and the detection model (full rescan) each independently cap the plugin well below 1M items.

---

## 4. If I designed it from scratch (target architecture for 10M+)

### 4.1 Guiding principles

1. **Never hold unbounded sets in PHP memory** — sets live in DB tables; PHP only ever touches a bounded chunk (≤ 5,000 rows).
2. **Every operation is chunked, checkpointed, resumable, idempotent.** A process may die at any instant; a restart resumes from the last cursor with no duplication.
3. **Event-driven invalidation beats full rescans.** The expensive full pipeline is a scheduled reconciliation, not the primary detection mechanism.
4. **Index every access path.** URL→ID resolution is a keyed lookup, never a scan.
5. **Destructive actions are batched, reversible, and logged.**
6. **The UI reports the truth** (real progress from job state), never a fabricated percentage.

### 4.2 Data model — three custom tables + a job queue

```sql
-- 1) Inventory: one row per attachment (replaces "SELECT all IDs" + snapshot option)
CREATE TABLE {$wpdb->prefix}mt_media (
  id            BIGINT UNSIGNED PRIMARY KEY,      -- attachment ID
  file_path     VARCHAR(512) NULL,                -- relative _wp_attached_file
  size_bytes    BIGINT UNSIGNED NULL,             -- from attachment metadata
  hash          CHAR(32) NULL,                    -- md5, filled lazily by dup job
  width/height  INT UNSIGNED NULL,
  mime          VARCHAR(100) NULL,
  updated_at    DATETIME NOT NULL,
  KEY idx_hash (hash), KEY idx_path (file_path(191))
);

-- 2) Usage edges: proof "attachment X is used at source Y" (replaces used_ids arrays)
CREATE TABLE {$wpdb->prefix}mt_usage (
  attachment_id BIGINT UNSIGNED NOT NULL,
  source_type   VARCHAR(32)  NOT NULL,   -- post_content|post_meta|option|widget|term|theme...
  source_id     BIGINT UNSIGNED NOT NULL, -- post_id / option_id / 0
  detector      VARCHAR(32)  NOT NULL,   -- exact|heuristic  (confidence!)
  detected_at   DATETIME NOT NULL,
  UNIQUE KEY uq_edge (attachment_id, source_type, source_id, detector(20)),
  KEY idx_source (source_type, source_id)
);

-- 3) URL index: kills attachment_url_to_postid() forever
CREATE TABLE {$wpdb->prefix}mt_url_index (
  url_path        VARCHAR(512) PRIMARY KEY,   -- '2026/09/photo-300x300.webp'
  attachment_id   BIGINT UNSIGNED NOT NULL,
  KEY idx_att (attachment_id)
);

-- 4) Job queue with checkpoints (replaces single cron event)
CREATE TABLE {$wpdb->prefix}mt_jobs (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type         VARCHAR(64) NOT NULL,   -- inventory|url_index|content_scan|meta_scan|reconcile|delete_batch|dup_hash
  payload      JSON NULL,
  state        VARCHAR(16) NOT NULL DEFAULT 'pending', -- pending|running|done|failed
  attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  checkpoint   JSON NULL,              -- {"cursor": 482100, "rows_done": 482000, "rows_total": 10000000}
  lease_until  DATETIME NULL,          -- running lock with expiry
  updated_at   DATETIME NOT NULL
);
```

**"Unused" becomes a SQL question, not a PHP array:**

```sql
SELECT m.id FROM mt_media m
LEFT JOIN mt_usage u ON u.attachment_id = m.id
WHERE u.attachment_id IS NULL;
```

No snapshot option. No diff. No memory. Scale is O(indexed join).

### 4.3 Execution model — time-boxed job runner

- Every job processes **one bounded chunk per run** (≤ 30 s, ≤ 5,000 rows), writes its **checkpoint**, then finishes.
- A single recurring cron tick (`mt_tick`, every minute) picks the next pending job (with a DB-level lease so only one worker runs). WP-CLI workers (`wp mt work`) for heavy sites.
- **Honest progress** = `SUM(rows_done) / SUM(rows_total)` from the job table → poll every 3–5 s (or SSE). The 99%-forever bug class becomes structurally impossible: the UI shows the real cursor position.
- Failure handling built-in: `attempts` + `lease_until` (stale leases auto-released), retry with backoff, failed jobs visible in UI.

Scan pipeline (phases = ordered jobs):

```
1. inventory     : stream wp_posts WHERE post_type=attachment AND ID > cursor → upsert mt_media
2. url_index     : stream _wp_attached_file + attachment metadata sizes → upsert mt_url_index
3. content_scan  : stream posts ID > cursor (ONE pass) → extract all patterns (below) → INSERT IGNORE mt_usage
4. meta_scan     : stream postmeta / options / widgets by PK cursor → same
5. reconcile     : LEFT JOIN query above → mark/list unused (materialized into a small result set or viewed live)
6. size_rollup   : SELECT SUM(size_bytes) FROM mt_media LEFT JOIN ... → stats
```

### 4.4 Content extraction — one pass, pluggable detectors

```php
interface Mt_Usage_Detector {
    public function slug(): string;                       // 'gutenberg_image', 'divi', 'elementor', 'acf', ...
    public function confidence(): string;                 // 'exact' | 'heuristic'
    /** @return int[] attachment IDs found in this chunk */
    public function extract( Mt_Content_Chunk $chunk ): array;
}
```

- A registry (`apply_filters( 'mt_usage_detectors', [...] )`) — core ships Gutenberg/shortcode/URL/Elementor/Divi/ACF/Woo detectors; third parties register their own instead of patching the monolith.
- The **content pass runs once per post row**; each detector matches its regexes against the same chunk. (Today: ~6 separate full passes.)
- URL-based matches resolve through **`mt_url_index`** (single keyed SELECT … IN, 500 at a time) — the per-URL `attachment_url_to_postid()` problem disappears permanently.
- `heuristic` matches (e.g., "bare number in postmeta") never delete directly — they route through verification (below).

### 4.5 The real scaling lever: incremental updates

Full pipeline only runs on install, on demand, or as scheduled reconciliation (e.g., weekly, chunked, at night). Day-to-day freshness comes from hooks:

| Hook | Reaction (small job) |
|------|----------------------|
| `add_attachment` / `wp_generate_attachment_metadata` | insert `mt_media` + `mt_url_index` rows |
| `delete_attachment` | remove inventory row + its usage edges |
| `save_post` (content changed) | delete edges for that post → rescan just that post → re-insert |
| `updated_post_meta` / `updated_option` (watched keys: `_thumbnail_id`, `_elementor_data`, `_et_pb_use_builder`, theme mods…) | revalidate affected attachment IDs only |

Revalidating one post = milliseconds. The user's "unused list" is effectively always current — **without ever rescanning the site**.

### 4.6 Safety model for deletion

1. **Preview** — selection UI shows detector + source ("used at: Divi module, post #123") and confidence.
2. **Dry-run diff** — list what *would* be deleted; store it.
3. **Trash, never force** — `wp_delete_attachment( $id )` (trash) in **batched background jobs** (500/ chuck), progress visible; `force` purge only after N days or explicit confirmation.
4. **Undo log** — `mt_deletions` table: id, file path, user, time, detector, restored flag. One-click restore (re-attach from trash / re-side-load file).
5. Only `exact` + verified matches are deletable by default; heuristic matches need explicit per-item approval.

### 4.7 Duplicate detection at scale

- Stage 1 (SQL): group by `(size_bytes, width, height)` — zero I/O, kills 90%+ of candidates.
- Stage 2: partial hash (first 64 KB) for survivors — small reads.
- Stage 3: full `md5_file` only when size + partial hash agree — tiny fraction.
- Hashing runs as a chunked background job (checkpoint per N files), not inline in a page request.

### 4.8 Multi-admin / concurrency

- Global **lease lock** row (`mt_jobs` lease + unique "pipeline" job type): only one scan pipeline can run site-wide; other admins see live progress of the same run (state is in DB, not per-user transient).
- Deletion jobs and scan jobs run under separate locks (delete depends on a fresh reconcile, enforced by job dependency).

### 4.9 Caching rules

- Cache **read-only report data** (dashboard stats) with versioned keys, invalidated by events (deletion, scan phase completion) — never cache authoritative pipeline state.
- Never delete cache by raw SQL on `_transient_%` rows; go through the cache API so Redis/Memcached stay consistent.
- No `unserialize()` without `array( 'allowed_classes' => false )` anywhere.

---

## 5. Migration roadmap (incremental, each phase ships value)

**Phase 0 — stabilize current code (days)**
- Already done: batched URL resolver, heartbeat + watchdog, Divi O(n²) fix, stale-fallback.
- Add: `allowed_classes => false` on all `unserialize()`; global scan lock; batched "Remove All" (500/job instead of one request); trash instead of force-delete.

**Phase 1 — move data out of options (1–2 releases)**
- Create `mt_media`, `mt_usage`, `mt_url_index`, `mt_jobs` (dbDelta + schema-version option).
- Implement job runner + inventory + url_index phases; keep existing detectors but run them from the chunked runner writing to `mt_usage`.
- Snapshot option becomes a query view; old option kept one release for rollback.

**Phase 2 — single-pass content scan + pluggable detectors**
- Merge the 11 loops into one pass; keyset pagination everywhere; detector registry; batched `INSERT IGNORE`.
- Duplicate detection: staged hashing pipeline.

**Phase 3 — incremental engine**
- Hook-based revalidation jobs; full scan demoted to scheduled reconciliation.
- Delete-safety layer: preview, trash, undo log.

**Phase 4 — scale-out**
- WP-CLI workers, optional queue backends (Action Scheduler adapter), multisite support, WP-CLI `wp mt verify` (compares old vs new detector on a sample → regression guard).

### Success criteria (10M media)

| Metric | Current | Target |
|---|---|---|
| Scan of 10M attachments | impossible (OOM/timeout) | completes in background over N chunked runs; resumable |
| Memory ceiling | unbounded (GBs) | constant (~64 MB) |
| Freshness after editing one post | full rescan | < 1 s (incremental job) |
| URL→ID resolution | 1 query per URL | 1 query per 500 URLs (indexed) |
| Delete of 100k files | one request, force, irreversible | background batches → trash, undo-able |
| Duplicate pass | full md5 of every file | SQL pre-group + partial hash on candidates |

---

## 6. Summary judgment

The current plugin is a reasonable **v1**: it works at small scale, ships fast, and its UX (one-click scan) is right. Its architecture, however, bets everything on three wrong assumptions — *one process is enough*, *an option row is a database*, and *full rescans are acceptable*. Any site that outgrows ~100k media items hits a wall that no amount of bug-fixing can remove (we already patched the worst symptoms: per-URL lookups, dead-process hangs, O(n²) loop).

The target design keeps the product identical from the user's point of view — click scan, see progress, delete safely — but moves the three bets to *chunked resumable jobs*, *indexed custom tables*, and *event-driven invalidation*. That design survives 10M items with constant memory and honest progress, and gives third-party builders a clean extension point instead of a 1,000-line function to patch.
