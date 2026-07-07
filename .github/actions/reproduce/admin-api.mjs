/**
 * Minimal Shopware Admin API client used by deterministic seed and HTTP paths.
 *
 * This module is intentionally MCP-independent: trusted replay must seed identically on every
 * reported version, including Shopware 6.6 where the remote MCP server does not exist. MCP only
 * assists the agent while it authors fixtures.
 *
 * Auth uses the first-party password grant available on a default install. Requests ask for
 * `application/json` because the repro helpers expect the flat response shape with ids at top level.
 */
import { adminPass, adminUser, appUrl, referencedPlaceholders, RUNTIME_PLACEHOLDERS } from './bundle.mjs';

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
let cachedPlaceholders = null;

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
  if (cachedPlaceholders) {
    return cachedPlaceholders;
  }
  const active = (field) => ({ limit: 1, filter: [{ type: 'equals', field, value: true }] });
  const orderState = (machine) => ({ limit: 1, filter: [
    { type: 'equals', field: 'technicalName', value: 'open' },
    { type: 'equals', field: 'stateMachine.technicalName', value: machine },
  ] });

  // All lookups are independent, so run them concurrently.
  const [
    sc, salutations, domains, country, tax, currency, domainLanguage,
    customerGroup, paymentMethod, shippingMethod, orderState_, orderDeliveryState, orderTransactionState,
  ] = await Promise.all([
    search('sales-channel', active('active')),
    search('salutation', { limit: 2 }),
    search('sales-channel-domain', { limit: 25 }),
    search('country', active('active')),
    search('tax', { limit: 1 }),
    search('currency', { limit: 1, filter: [{ type: 'equals', field: 'isoCode', value: 'EUR' }] }),
    search('sales-channel-domain', { limit: 1 }),
    search('customer-group', { limit: 1 }),
    search('payment-method', active('active')),
    search('shipping-method', active('active')),
    search('state-machine-state', orderState('order.state')),
    search('state-machine-state', orderState('order_delivery.state')),
    search('state-machine-state', orderState('order_transaction.state')),
  ]);

  const preferredDomain = (() => {
    const urls = (domains.data || []).map((d) => d.url).filter(Boolean);
    return urls.find((u) => u === base()) || urls.find((u) => /^https?:\/\//.test(u)) || urls[0] || base();
  })();

  cachedPlaceholders = {
    SC: firstId(sc),
    NAV_CAT: firstId(sc, 'navigationCategoryId'),
    STOREFRONT_URL: preferredDomain,
    COUNTRY: firstId(country),
    SALUTATION: salutations.data?.[0]?.id ?? '',
    SALUTATION2: salutations.data?.[1]?.id ?? salutations.data?.[0]?.id ?? '',
    TAX: firstId(tax),
    CURRENCY: firstId(currency),
    SYSTEM_LANGUAGE: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
    LANGUAGE: firstId(domainLanguage, 'languageId') || '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
    CUSTOMER_GROUP: firstId(customerGroup),
    PAYMENT_METHOD: firstId(paymentMethod),
    SHIPPING_METHOD: firstId(shippingMethod),
    ORDER_STATE_OPEN: firstId(orderState_),
    ORDER_DELIVERY_STATE_OPEN: firstId(orderDeliveryState),
    ORDER_TRANSACTION_STATE_OPEN: firstId(orderTransactionState),
  };
  return cachedPlaceholders;
}

export async function resolveBundlePlaceholders({ values, includeSalesChannelAccessKey = false }) {
  const referenced = referencedPlaceholders(...values);
  const needsIds = referenced.some((key) => !RUNTIME_PLACEHOLDERS.includes(key));

  let ids = { STOREFRONT_URL: appUrl() };
  if (needsIds) {
    ids = { ...await resolvePlaceholders() };
  }

  ids.STOREFRONT_URL = ids.STOREFRONT_URL || appUrl();
  ids.SW_ACCESS_KEY = process.env.SW_ACCESS_KEY || (includeSalesChannelAccessKey ? await salesChannelAccessKey() : '');
  ids.SW_CONTEXT_TOKEN = '';

  return ids;
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
 * @returns A non-throwing Sync API outcome: success status on accepted operations, or validation
 * detail that explains why seeding failed.
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
  return { ok: false, status: res.status, detail: await errorDetail(res) };
}

/**
 * Extracts an actionable message from a failed Admin API response.
 *
 * The body can only be read once, so this reads it as text and then tries JSON so both structured
 * `errors[].detail` responses and plain-text error pages surface a reason (a bare `await res.json()`
 * followed by `res.text()` would throw "Body is unusable" and lose all non-JSON detail).
 */
async function errorDetail(res) {
  const raw = await res.text().catch(() => '');
  try {
    const detail = JSON.parse(raw)?.errors?.map((e) => e.detail).filter(Boolean).join('; ');
    if (detail) {
      return detail;
    }
  } catch {
    // Not JSON — fall back to the raw text below.
  }
  return raw.slice(0, 300);
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
  return { ok: false, status: res.status, detail: await errorDetail(res) };
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
