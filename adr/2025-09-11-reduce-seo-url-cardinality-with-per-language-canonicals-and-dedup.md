---
title: Reduce SEO URL cardinality with per-language canonicals and deduplication
date: 2025-09-11
area: inventory
tags: [seo, performance, database, storefront, sales-channel, language]
---

## Context

Shops with many sales channels and languages experience exponential growth of the `seo_url` table. For each entity (e.g., product, category), Shopware generates canonical SEO URLs per `(language_id × sales_channel_id)` combination.

Example: 11 sales channels × 9 languages = 99 canonical rows per entity. In many setups, slugs are identical across sales channels because:

- The same URL template is used in all sales channels; and
- There is no sales-channel-specific main category structure applied to the slug.

This creates redundant data, leading to:

- Increased storage usage and slower queries (wider unique indexes, more rows to scan/sort),
- Higher write amplification for SEO updates, and
- Overall performance degradation around SEO URL resolution and canonical redirects.

## Decision

We adopt a language-first canonical model with an operational deduplication command to safely reduce duplicate canonical SEO URLs while keeping backward compatibility for shops that require per–sales-channel URLs.

Key parts:

- Resolver fallback (read path):
  - Prefer the current request language; if not found, fall back to the system default language (`Defaults::LANGUAGE_SYSTEM`).
  - When finding canonical URLs, consider both sales-channel-specific entries and global entries (`sales_channel_id IS NULL`).
  - Result ordering prefers current language, then sales-channel-specific entries over global ones.

- Deduplication command (operational path):
  - Console command `seo:deduplicate` detects duplicate canonical SEO URLs using a language-agnostic grouping key of `(route_name, path_info, seo_path_info)` with `is_canonical = 1`.
  - For each duplicate group, the command keeps exactly one row and removes the others:
    - If `--prefer-default-keeper` is set and a default-language row exists, it is kept (preferring a global entry among default-language rows if present, else the oldest default-language row).
    - Otherwise, keep a global row if present (`sales_channel_id IS NULL`); if none, keep the oldest row by `created_at`.
    - Redundant rows are either soft-deleted (unset `is_canonical`, set `is_deleted = 1`) with `--soft-delete`, or physically removed with `--hard-delete`.
  - `--non-default-only` restricts selection and deletion to non‑default language rows while still detecting duplicates across all languages.
  - `--list` and `--dry-run` allow safe inspection before applying changes.

- Generation behavior (write path):
  - Configurable default-language-only generation: when `core.seo.generateOnlyDefaultLanguage = true`, generation produces canonicals only for the system default language (`Defaults::LANGUAGE_SYSTEM`) per sales channel, reducing per-language rows at write time.
  - Default remains `false` to avoid surprises; shops can opt in based on requirements for localized slugs.

No schema changes are required.

## Consequences

Benefits:

- Significant row reduction in `seo_url` for shops where slugs are identical across sales channels.
- Faster resolution and canonical lookups due to smaller working sets and simpler result ordering.
- Lower storage usage and improved index efficiency.
- With `core.seo.generateOnlyDefaultLanguage = true`, new entities generate only default-language SEO URLs; resolver fallback serves them for other languages.
- Backward compatible: shops with sales-channel–specific slugs (different template or main category configuration) remain unaffected because deduplication only collapses strictly identical entries.

Trade-offs and risks:

- If two sales channels intentionally share identical slugs for the same entity, deduplication collapses them into a single global entry. Functionally safe (identical URL), but visibility of per-channel rows is reduced in raw data.
- Deduplication is operational (command-driven). Data can drift as new SEO URLs are generated until the command is run again (can be scheduled periodically), unless default-language-only generation is enabled.
- If default-language-only generation is enabled, shops will not have localized slugs for non-default languages; URLs fall back to the default language.

Operational notes:

- Dry-run support (`--dry-run`) and listing (`--list`) allow safe previewing of impact.
- Duplicates can be removed via soft delete or hard delete:
  - Soft delete unsets `is_canonical` and sets `is_deleted = 1`. Safer and auditable.
  - Hard delete removes rows with `DELETE`. Immediate compaction for very large datasets. Irreversible; use `--dry-run` first.
- Deletions run in a single retryable transaction – either all changes apply or none.
- Foreign keys: `seo_url` is not referenced by other core tables with FKs, so removing duplicates does not violate referential integrity. Custom extensions should be reviewed before using hard-delete.
- To focus cleanup on localized data, use `--non-default-only` to act only on non-default language rows.
- Canonical redirect behavior remains consistent; global canonicals are still considered by resolvers.
- To enable default-language-only generation, set `core.seo.generateOnlyDefaultLanguage = true` through `system_config`.

Rollout guide:

1) Prepare and assess in staging
   - Optional: Enable `core.seo.generateOnlyDefaultLanguage = true` if you do not need localized slugs. This reduces future writes.
   - Run a dry-run and/or list to estimate impact and review planned changes:
     - `bin/console seo:deduplicate --route=frontend.detail.page --route=frontend.navigation.page --dry-run`
     - `bin/console seo:deduplicate --route=frontend.detail.page --route=frontend.navigation.page --list`
   - Inspect output (duplicate groups and rows) and verify sample entities resolve correctly.

2) Apply with soft-delete first (recommended)
   - Execute with `--soft-delete` to remove duplicates while keeping one canonical per group:
     - `bin/console seo:deduplicate --route=frontend.detail.page --route=frontend.navigation.page --soft-delete`
     - Optionally add `--prefer-default-keeper` or `--non-default-only` depending on your policy.
   - Validate storefront resolution and redirects. Monitor logs for unexpected 404/redirect loops.

3) Optional: Apply hard-delete for immediate compaction
   - Back up the database or ensure snapshotting.
   - Re-run with `--hard-delete` to physically remove duplicates:
     - `bin/console seo:deduplicate --route=frontend.detail.page --route=frontend.navigation.page --hard-delete`
     - Combine with `--prefer-default-keeper` or `--non-default-only` as needed.
   - Prefer off-peak hours for large datasets; the command runs in a single retryable transaction per execution.

4) Operate
   - Warm caches if necessary.
   - Optionally schedule periodic deduplication (soft-delete mode) to keep data compact as new SEO URLs are generated.

## Examples

- List duplicate groups for product detail pages
  - `bin/console seo:deduplicate --route=frontend.detail.page --list`

Example output (abridged):

```
Duplicate groups found: 3
Redundant canonical entries to delete: 4
Duplicate group: lang=2fbb5fe2e29a4d70aa5854ce7ce3e20b route=frontend.detail.page path='/detail/0199560605d3...' seo='Small-Plastic-Potter-Fodder/SW10000' (rows=2)
  id=019956066487723798ab2de2d6050daa global=0 createdAt=2025-09-17 04:54:43.558
  id=0199560d4db2735fbcafe7cab52199ef global=0 createdAt=2025-09-17 05:02:16.493
...
Duplicate groups listed: 3
```

- Soft delete redundant rows, keeping one per group
  - `bin/console seo:deduplicate --route=frontend.detail.page --soft-delete`

- Prefer keeping default-language entries (when present)
  - `bin/console seo:deduplicate --route=frontend.detail.page --soft-delete --prefer-default-keeper`

- Delete only non-default language rows in duplicate groups
  - `bin/console seo:deduplicate --route=frontend.detail.page --soft-delete --non-default-only`

## SQL Appendix

Core duplicate selection (simplified):

```sql
SELECT
  LOWER(HEX(su.id))             AS id,
  LOWER(HEX(su.language_id))    AS languageId,
  LOWER(HEX(su.foreign_key))    AS foreignKey,
  su.route_name                 AS routeName,
  su.path_info                  AS pathInfo,
  su.seo_path_info              AS seoPathInfo,
  (su.sales_channel_id IS NULL) AS isGlobal,
  su.created_at                 AS createdAt
FROM seo_url su
JOIN (
  SELECT route_name, path_info, seo_path_info
  FROM seo_url
  WHERE is_canonical = 1
    /* optional: AND route_name IN (:routes) */
  GROUP BY route_name, path_info, seo_path_info
  HAVING COUNT(*) > 1
) d
  ON d.route_name = su.route_name
 AND d.path_info = su.path_info
 AND d.seo_path_info = su.seo_path_info
WHERE su.is_canonical = 1
  /* optional: AND su.route_name IN (:routes) */
  /* optional: AND su.language_id != :defaultLang (for --non-default-only) */
ORDER BY su.route_name, su.path_info, su.seo_path_info,
         (su.sales_channel_id IS NULL) DESC,
         su.created_at ASC;
```

Notes:

- Grouping is language-agnostic and matches the command behavior.
- Collation on `seo_path_info` is typically `utf8mb4_unicode_ci`, which is case-insensitive.
- The command then applies keeper policy in PHP according to flags (`--prefer-default-keeper`, etc.).

Alternatives considered:

- Always generate one canonical per language (skip sales-channel rows): rejected as a default to avoid breaking shops that rely on per–sales-channel slugs (e.g., category-based paths that differ per channel).
- Database-level partial indexes or constraints: adds complexity and may not reflect business rules (e.g., only dedupe when slugs are truly identical).
- Event-driven deduplication at write time: more invasive and harder to reason about; batching via a command is simpler and safer operationally.
