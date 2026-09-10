/**
 * Seed command for applying exactly the fixture entities a repro needs through Admin Sync API.
 *
 * It resolves placeholders against the running shop, upserts static fixtures, uploads any media
 * bytes, and refreshes storefront indexes. Bad payloads write API validation detail to
 * `seed-error.txt` and throw so both agent preview and official legs get an actionable blocker.
 */
import fs from 'node:fs';
import path from 'node:path';
import { FILES, ENTITY_PLACEHOLDERS, STABLE_IDS, readJson, fillPlaceholders, unresolvedPlaceholders } from '../../bundle.ts';
import { resolvePlaceholders, sync, uploadMedia, refreshIndexes } from '../../admin-api.ts';

/**
 * A single Admin Sync API operation envelope (or an authoring shortcut resolving into one).
 */
interface SyncOperation {
  entity?: string;
  action?: string;
  payload?: unknown[];
  [key: string]: unknown;
}

/**
 * The authored fixtures.json shape: entity keys mapping to a bare payload array or a Sync envelope,
 * plus the optional `_repro_media_uploads` list handled separately.
 */
type Fixtures = Record<string, unknown[] | SyncOperation>;

/**
 * One `_repro_media_uploads` entry: a media row id plus the bundle-relative file to attach.
 */
interface MediaEntry {
  mediaId: string;
  path: string;
  extension: string;
  mimeType: string;
  fileName?: string;
  [key: string]: unknown;
}

/**
 * Converts Admin API-style entity names to Sync API operation entity names.
 */
const snakeCase = (name: string) => name.replace(/-/g, '_');

/**
 * Storefront indexers affected by each seeded entity type.
 *
 * Keys omitted here either do not affect rendered storefront state for repro purposes or are handled
 * by the Sync operation itself.
 */
const ENTITY_INDEXERS: Record<string, string[]> = {
  product: ['product.indexer', 'category.indexer', 'product_stream.indexer'],
  category: ['category.indexer'],
  product_stream: ['product_stream.indexer'],
  landing_page: ['landing_page.indexer'],
};

class SeedError extends Error {
  constructor(message: string) {
    super(message);
    fs.writeFileSync(FILES.seedError, message.slice(0, 400));
  }
}

/**
 * Normalizes fixture authoring shortcuts into Admin Sync API operations.
 *
 * Agents may write either a bare payload array or a full operation envelope; this helper preserves
 * both while converting hyphenated entity names to the snake-case names expected by Sync API.
 *
 * @example
 * const operations = toSyncOperations({ product: [{ id: '...' }] });
 * console.log(operations.product.entity); // "product"
 */
function toSyncOperations(fixtures: Fixtures): Record<string, SyncOperation> {
  const operations: Record<string, SyncOperation> = {};
  for (const [key, value] of Object.entries(fixtures)) {
    if (key === '_repro_media_uploads') {
      continue;
    }
    operations[key] = Array.isArray(value)
      ? { entity: snakeCase(key), action: 'upsert', payload: value }
      : { ...value, entity: snakeCase(value.entity || key) };
  }
  return operations;
}

/**
 * Applies deterministic fixture state to the current Shopware leg.
 *
 * Use this before an executor runs so both reported and trunk legs receive the same static rows,
 * uploaded media bytes, and refreshed storefront indexes.
 *
 * @example
 * try {
 *   await seed({ fixturesPath: FILES.fixtures });
 * } catch (err) {
 *   return fail(target, out, `seeding fixtures.json failed: ${err.message}`);
 * }
 */
export async function seed({ fixturesPath = FILES.fixtures } = {}) {
  if (!fs.existsSync(fixturesPath)) {
    console.log(`no fixtures (${fixturesPath}) — nothing to seed`);
    return;
  }

  let fixtures: Fixtures;
  try {
    fixtures = readJson<Fixtures>(fixturesPath);
  } catch {
    throw new SeedError('fixtures.json is not valid JSON');
  }

  const operations = toSyncOperations(fixtures);
  const ids = await resolvePlaceholders();
  const rawOperations = JSON.stringify(operations);

  // Reject install ids here so the fix is visible before the official leg blocks later.
  for (const key of ENTITY_PLACEHOLDERS) {
    const value = ids[key];
    // Cross-install constants are portable, so skip the install-id guard for them.
    if (value && !STABLE_IDS.has(value) && rawOperations.includes(value)) {
      throw new SeedError(
        `fixtures hardcode the install {{${key}}} id (${value}); `
        + 'reference it with the placeholder — every provisioned shop generates different UUIDs',
      );
    }
    if (rawOperations.includes(`{{${key}}}`) && !value) {
      throw new SeedError(`could not resolve {{${key}}} (admin search returned empty)`);
    }
  }

  const resolved: unknown = JSON.parse(fillPlaceholders(rawOperations, ids));
  const leftover = unresolvedPlaceholders(JSON.stringify(resolved));
  if (leftover.length) {
    throw new SeedError(`unresolved placeholder(s) in fixtures: ${leftover.join(', ')}`);
  }

  const result = await sync(resolved);
  if (!result.ok) {
    throw new SeedError(`sync HTTP ${result.status}: ${result.detail || ''}`);
  }
  console.log(`seeded OK (sync HTTP ${result.status})`);

  await uploadFixtureMedia(fixtures._repro_media_uploads, ids);

  // Only run indexers that the seeded entities can affect.
  const indexers = [...new Set(
    Object.keys(operations).flatMap((key) => ENTITY_INDEXERS[key] ?? []),
  )];
  if (indexers.length) {
    await refreshIndexes(indexers);
    console.log(`refreshed storefront indexes: ${indexers.join(', ')}`);
  }
}

/**
 * Uploads fixture media bytes after Sync API has created the media rows.
 *
 * Media rows alone do not create file-backed thumbnails or replaceable assets, so this step attaches
 * actual bytes before browser flows open the Media library.
 */
async function uploadFixtureMedia(uploads: unknown, ids: Record<string, string>) {
  if (!Array.isArray(uploads) || uploads.length === 0) {
    return;
  }
  console.log(`uploading ${uploads.length} fixture media file(s)…`);
  for (const raw of uploads) {
    const entry: MediaEntry = JSON.parse(fillPlaceholders(JSON.stringify(raw), ids));
    for (const field of ['mediaId', 'path', 'extension', 'mimeType']) {
      if (!entry[field]) {
        throw new SeedError(`_repro_media_uploads entry requires ${field}`);
      }
    }
    // The media bytes are read from the host with a fixture-controlled path. Confine it to the
    // bundle's `media/` dir and require a regular file, so a crafted `path` (absolute, or `../`
    // traversal) can't read an arbitrary host file (e.g. /etc/passwd) and upload it. Author media
    // under `media/` in the bundle and reference it relative to that dir (e.g. "logo.png").
    const mediaRoot = path.resolve('media');
    const resolved = path.resolve(mediaRoot, entry.path);
    if (resolved !== mediaRoot && !resolved.startsWith(mediaRoot + path.sep)) {
      throw new SeedError(`_repro_media_uploads path must be inside the bundle media/ dir (got '${entry.path}')`);
    }
    let stat = null;
    try {
      stat = fs.statSync(resolved);
    } catch {
      stat = null;
    }
    if (!stat || !stat.isFile()) {
      throw new SeedError(`_repro_media_uploads path is not a regular file under media/: ${entry.path}`);
    }
    const result = await uploadMedia({ ...entry, path: resolved });
    if (!result.ok) {
      throw new SeedError(`media upload HTTP ${result.status} for ${entry.mediaId}: ${result.detail || ''}`);
    }
  }
}
