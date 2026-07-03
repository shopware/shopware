// Seed exactly the entities a repro needs via the Admin Sync API — no demodata. Reads fixtures.json,
// resolves {{PLACEHOLDER}} ids against the running shop, upserts, uploads any fixture media, and
// refreshes storefront indexes so seeded entities are visible. Idempotent.
//
// Fails LOUD: a bad payload writes the API's validation detail to seed-error.txt and throws, so the
// agent (via `try`) and the report (via a blocked leg) both get an actionable reason.
import fs from 'node:fs';
import { FILES, ENTITY_PLACEHOLDERS, readJson, fillPlaceholders, unresolvedPlaceholders } from './lib.mjs';
import { resolvePlaceholders, sync, uploadMedia, refreshIndexes } from './admin-api.mjs';

/**
 * Converts Admin API-style entity names to Sync API operation entity names.
 */
const snakeCase = (name) => name.replace(/-/g, '_');

class SeedError extends Error {
  constructor(message) {
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
function toSyncOperations(fixtures) {
  const operations = {};
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

  let fixtures;
  try {
    fixtures = readJson(fixturesPath);
  } catch {
    throw new SeedError('fixtures.json is not valid JSON');
  }

  const operations = toSyncOperations(fixtures);
  const ids = await resolvePlaceholders();
  const rawOperations = JSON.stringify(operations);

  // A literal install id seeds on this shop but FK-fails on the freshly-provisioned legs — reject it
  // so the failure surfaces here, with the placeholder to use, instead of as a mystery SQL 1452 later.
  for (const key of ENTITY_PLACEHOLDERS) {
    const value = ids[key];
    if (value && rawOperations.includes(value)) {
      throw new SeedError(
        `fixtures hardcode the install {{${key}}} id (${value}); `
        + 'reference it with the placeholder — every provisioned shop generates different UUIDs',
      );
    }
    if (rawOperations.includes(`{{${key}}}`) && !value) {
      throw new SeedError(`could not resolve {{${key}}} (admin search returned empty)`);
    }
  }

  const resolved = JSON.parse(fillPlaceholders(rawOperations, ids));
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
  await refreshIndexes();
  console.log('refreshed storefront indexes');
}

/**
 * Uploads fixture media bytes after Sync API has created the media rows.
 *
 * Media rows alone do not create file-backed thumbnails or replaceable assets, so this step attaches
 * actual bytes before browser flows open the Media library.
 */
async function uploadFixtureMedia(uploads, ids) {
  if (!Array.isArray(uploads) || uploads.length === 0) {
    return;
  }
  console.log(`uploading ${uploads.length} fixture media file(s)…`);
  for (const raw of uploads) {
    const entry = JSON.parse(fillPlaceholders(JSON.stringify(raw), ids));
    for (const field of ['mediaId', 'path', 'extension', 'mimeType']) {
      if (!entry[field]) {
        throw new SeedError(`_repro_media_uploads entry requires ${field}`);
      }
    }
    if (!fs.existsSync(entry.path)) {
      throw new SeedError(`_repro_media_uploads path does not exist: ${entry.path}`);
    }
    const result = await uploadMedia(entry);
    if (!result.ok) {
      throw new SeedError(`media upload HTTP ${result.status} for ${entry.mediaId}: ${result.detail || ''}`);
    }
  }
}
