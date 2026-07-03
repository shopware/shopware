// Minimal Shopware Admin API client used by the deterministic seed/HTTP paths. It is intentionally
// MCP-independent: the trusted pipeline must seed identically on every reported version (incl. 6.6
// and older, where the Shopware MCP server does not exist) with no agent or bridge present. MCP only
// assists the agent while it authors fixtures.
//
// Auth is the first-party password grant that works on a default install (admin / shopware). All
// requests use Accept: application/json for the flat response shape (id at the top level).
import { adminPass, adminUser, appUrl } from './lib.mjs';

/**
 * Returns the Admin API base URL for the current provisioned shop.
 */
const base = () => {
  const url = appUrl();
  if (!url) {
    throw new Error('APP_URL is required');
  }
  return url;
};

let cachedToken = '';

/**
 * Returns an Admin API bearer token for deterministic seeding and HTTP execution.
 *
 * The token is cached per process because a leg may perform many placeholder lookups and Sync API
 * calls, all against the same provisioned shop.
 *
 * @example
 * const headers = { Authorization: `Bearer ${await token()}` };
 */
export async function token() {
  if (cachedToken) {
    return cachedToken;
  }
  const res = await fetch(`${base()}/api/oauth/token`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      grant_type: 'password',
      client_id: 'administration',
      username: adminUser(),
      password: adminPass(),
      scopes: 'write',
    }),
  });
  const data = await res.json().catch(() => ({}));
  if (!data.access_token) {
    throw new Error(`admin OAuth token request failed (HTTP ${res.status})`);
  }
  cachedToken = data.access_token;
  return cachedToken;
}

/**
 * Builds JSON Admin API headers with the cached bearer token.
 */
const authHeaders = async () => ({
  Authorization: `Bearer ${await token()}`,
  Accept: 'application/json',
  'Content-Type': 'application/json',
});

/**
 * Searches one Admin API entity and returns the flat JSON API response.
 *
 * Placeholder resolution uses this instead of DAL internals so the deterministic runner works
 * against any provisioned Shopware version with Admin API credentials.
 */
export async function search(entity, criteria) {
  const res = await fetch(`${base()}/api/search/${entity}`, {
    method: 'POST',
    headers: await authHeaders(),
    body: JSON.stringify(criteria),
  });
  if (!res.ok) {
    throw new Error(`admin search ${entity} failed (HTTP ${res.status})`);
  }
  return res.json();
}

/**
 * Extracts the first id-like field from an Admin API search result.
 */
const firstId = (result, field = 'id') => result?.data?.[0]?.[field] ?? '';

/**
 * Resolves every portable bundle placeholder to a live id on the current Shopware leg.
 *
 * Fixtures and HTTP plans use these values instead of install-specific UUIDs; stable constants such
 * as `SYSTEM_LANGUAGE` are returned directly while generated entities are read through Admin search.
 *
 * @example
 * const ids = await resolvePlaceholders();
 * const body = fillPlaceholders(JSON.stringify(fixtures), ids);
 */
export async function resolvePlaceholders() {
  const active = (field) => ({ limit: 1, filter: [{ type: 'equals', field, value: true }] });
  const orderState = (machine) => ({ limit: 1, filter: [
    { type: 'equals', field: 'technicalName', value: 'open' },
    { type: 'equals', field: 'stateMachine.technicalName', value: machine },
  ] });

  const sc = await search('sales-channel', active('active'));
  const salutations = await search('salutation', { limit: 2 });
  const domains = await search('sales-channel-domain', { limit: 25 });
  const preferredDomain = (() => {
    const urls = (domains.data || []).map((d) => d.url).filter(Boolean);
    return urls.find((u) => u === base()) || urls.find((u) => /^https?:\/\//.test(u)) || urls[0] || base();
  })();

  return {
    SC: firstId(sc),
    NAV_CAT: firstId(sc, 'navigationCategoryId'),
    STOREFRONT_URL: preferredDomain,
    COUNTRY: firstId(await search('country', active('active'))),
    SALUTATION: salutations.data?.[0]?.id ?? '',
    SALUTATION2: salutations.data?.[1]?.id ?? salutations.data?.[0]?.id ?? '',
    TAX: firstId(await search('tax', { limit: 1 })),
    CURRENCY: firstId(await search('currency', { limit: 1, filter: [{ type: 'equals', field: 'isoCode', value: 'EUR' }] })),
    SYSTEM_LANGUAGE: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
    LANGUAGE: firstId(await search('sales-channel-domain', { limit: 1 }), 'languageId') || '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
    CUSTOMER_GROUP: firstId(await search('customer-group', { limit: 1 })),
    PAYMENT_METHOD: firstId(await search('payment-method', active('active'))),
    SHIPPING_METHOD: firstId(await search('shipping-method', active('active'))),
    ORDER_STATE_OPEN: firstId(await search('state-machine-state', orderState('order.state'))),
    ORDER_DELIVERY_STATE_OPEN: firstId(await search('state-machine-state', orderState('order_delivery.state'))),
    ORDER_TRANSACTION_STATE_OPEN: firstId(await search('state-machine-state', orderState('order_transaction.state'))),
  };
}

/**
 * Resolves an active sales-channel access key for Store API executor requests.
 *
 * The HTTP executor injects this value when the plan targets `/store-api/*` and did not provide a
 * leg-specific `SW_ACCESS_KEY` environment override.
 */
export async function salesChannelAccessKey() {
  const sc = await search('sales-channel', { limit: 5, filter: [{ type: 'equals', field: 'active', value: true }] });
  return (sc.data || []).map((c) => c.accessKey).find(Boolean) || '';
}

/**
 * Sends a prepared Sync API operation envelope and returns a non-throwing result object.
 *
 * Seeding wants actionable API validation detail in `seed-error.txt`, so callers receive
 * `{ ok, status, detail }` instead of an exception with a lost response body.
 *
 * @example
 * const result = await sync(toSyncOperations(fixtures));
 * if (!result.ok) {
 *   throw new SeedError(`sync HTTP ${result.status}: ${result.detail}`);
 * }
 */
export async function sync(operations) {
  const res = await fetch(`${base()}/api/_action/sync`, {
    method: 'POST',
    headers: await authHeaders(),
    body: JSON.stringify(operations),
  });
  if (res.status === 200 || res.status === 204) {
    return { ok: true, status: res.status };
  }
  const body = await res.json().catch(() => null);
  const detail = body?.errors?.map((e) => e.detail).filter(Boolean).join('; ') || (await res.text().catch(() => '')).slice(0, 300);
  return { ok: false, status: res.status, detail };
}

/**
 * Uploads raw fixture bytes onto an already-seeded media entity.
 *
 * Sync API creates the media row, but browser media flows need `hasFile`/thumbnail state backed by
 * actual uploaded bytes before the leg opens the Media library.
 */
export async function uploadMedia({ mediaId, path, extension, mimeType, fileName }) {
  const fs = await import('node:fs');
  const query = new URLSearchParams({ extension });
  if (fileName) {
    query.set('fileName', fileName);
  }
  const res = await fetch(`${base()}/api/_action/media/${mediaId}/upload?${query}`, {
    method: 'POST',
    headers: { Authorization: `Bearer ${await token()}`, Accept: 'application/json', 'Content-Type': mimeType },
    body: fs.readFileSync(path),
  });
  if ([200, 204, 302].includes(res.status)) {
    return { ok: true };
  }
  const body = await res.json().catch(() => null);
  return { ok: false, status: res.status, detail: body?.errors?.map((e) => e.detail).join('; ') || '' };
}

/**
 * Runs storefront-relevant indexers after fixture seeding.
 *
 * CI has no queue worker, so freshly synced products, categories, and pages may stay invisible until
 * these Admin API indexer calls complete inside the live Shopware process.
 */
export async function refreshIndexes(indexers = ['category.indexer', 'product.indexer', 'product_stream.indexer', 'landing_page.indexer']) {
  const headers = await authHeaders();
  for (const indexer of indexers) {
    let offset = 0;
    for (let step = 0; step < 1000; step += 1) {
      const res = await fetch(`${base()}/api/_action/indexing/${indexer}`, {
        method: 'POST',
        headers,
        body: JSON.stringify({ offset }),
      });
      if (!res.ok) {
        console.warn(`::warning::indexer ${indexer} HTTP ${res.status}`);
        break;
      }
      const data = await res.json().catch(() => ({}));
      if (data.finish === true) {
        break;
      }
      const next = data.offset?.offset ?? data.offset ?? null;
      if (next === null) {
        break;
      }
      offset = next;
    }
  }
}
