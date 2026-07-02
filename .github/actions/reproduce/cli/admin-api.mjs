// Minimal Shopware Admin API client used by the deterministic seed/HTTP paths. It is intentionally
// MCP-independent: the trusted pipeline must seed identically on every reported version (incl. 6.6
// and older, where the Shopware MCP server does not exist) with no agent or bridge present. MCP only
// assists the agent while it authors fixtures.
//
// Auth is the first-party password grant that works on a default install (admin / shopware). All
// requests use Accept: application/json for the flat response shape (id at the top level).
import { appUrl } from './lib.mjs';

const base = () => {
  const url = appUrl();
  if (!url) throw new Error('APP_URL is required');
  return url;
};
const adminUser = () => process.env.ADMIN_USER || 'admin';
const adminPass = () => process.env.ADMIN_PASS || 'shopware';

let cachedToken = '';

export async function token() {
  if (cachedToken) return cachedToken;
  const res = await fetch(`${base()}/api/oauth/token`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ grant_type: 'password', client_id: 'administration', username: adminUser(), password: adminPass(), scopes: 'write' }),
  });
  const data = await res.json().catch(() => ({}));
  if (!data.access_token) throw new Error(`admin OAuth token request failed (HTTP ${res.status})`);
  cachedToken = data.access_token;
  return cachedToken;
}

const authHeaders = async () => ({ Authorization: `Bearer ${await token()}`, Accept: 'application/json', 'Content-Type': 'application/json' });

// POST /api/search/<entity> → flat search response.
export async function search(entity, criteria) {
  const res = await fetch(`${base()}/api/search/${entity}`, { method: 'POST', headers: await authHeaders(), body: JSON.stringify(criteria) });
  if (!res.ok) throw new Error(`admin search ${entity} failed (HTTP ${res.status})`);
  return res.json();
}

const firstId = (result, field = 'id') => result?.data?.[0]?.[field] ?? '';

// Resolve every {{PLACEHOLDER}} to a live id on the running shop. Mirrors the ids fixtures/HTTP
// plans reference. SYSTEM_LANGUAGE is Shopware's stable Defaults::LANGUAGE_SYSTEM constant.
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

// Resolve an active sales-channel access key for store-api auth.
export async function salesChannelAccessKey() {
  const sc = await search('sales-channel', { limit: 5, filter: [{ type: 'equals', field: 'active', value: true }] });
  return (sc.data || []).map((c) => c.accessKey).find(Boolean) || '';
}

// POST /api/_action/sync with the operation envelope. Returns { ok, status, detail }.
export async function sync(operations) {
  const res = await fetch(`${base()}/api/_action/sync`, { method: 'POST', headers: await authHeaders(), body: JSON.stringify(operations) });
  if (res.status === 200 || res.status === 204) return { ok: true, status: res.status };
  const body = await res.json().catch(() => null);
  const detail = body?.errors?.map((e) => e.detail).filter(Boolean).join('; ') || (await res.text().catch(() => '')).slice(0, 300);
  return { ok: false, status: res.status, detail };
}

// Upload raw bytes onto an already-seeded media entity.
export async function uploadMedia({ mediaId, path, extension, mimeType, fileName }) {
  const fs = await import('node:fs');
  const query = new URLSearchParams({ extension });
  if (fileName) query.set('fileName', fileName);
  const res = await fetch(`${base()}/api/_action/media/${mediaId}/upload?${query}`, {
    method: 'POST',
    headers: { Authorization: `Bearer ${await token()}`, Accept: 'application/json', 'Content-Type': mimeType },
    body: fs.readFileSync(path),
  });
  if ([200, 204, 302].includes(res.status)) return { ok: true };
  const body = await res.json().catch(() => null);
  return { ok: false, status: res.status, detail: body?.errors?.map((e) => e.detail).join('; ') || '' };
}

// Storefront indexers don't run on their own in CI (no queue worker), so freshly-synced
// products/categories/pages won't appear in listings until indexed. Drive them via the Admin API
// (runs inside the live process, works from the sandbox).
export async function refreshIndexes(indexers = ['category.indexer', 'product.indexer', 'product_stream.indexer', 'landing_page.indexer']) {
  const headers = await authHeaders();
  for (const indexer of indexers) {
    let offset = 0;
    for (let step = 0; step < 1000; step += 1) {
      const res = await fetch(`${base()}/api/_action/indexing/${indexer}`, { method: 'POST', headers, body: JSON.stringify({ offset }) });
      if (!res.ok) { console.warn(`::warning::indexer ${indexer} HTTP ${res.status}`); break; }
      const data = await res.json().catch(() => ({}));
      if (data.finish === true) break;
      const next = data.offset?.offset ?? data.offset ?? null;
      if (next === null) break;
      offset = next;
    }
  }
}
