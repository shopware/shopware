import { test, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { installMockFetch } from './tests/helpers/mock-fetch.ts';
import type { RouteFn } from './tests/helpers/mock-fetch.ts';
import { search, refreshIndexes } from './admin-api.ts';

// admin-api talks to a live shop, so every test runs against a stubbed global fetch. token() caches
// its bearer at module scope, so an OAuth route is always provided and the first test warms the cache.
let mock: ReturnType<typeof installMockFetch> | undefined;
beforeEach(() => { process.env.APP_URL = 'http://shop.test'; });
afterEach(() => { mock?.restore(); });

const withOAuth = (route: RouteFn): RouteFn => (url, init) =>
  (url.endsWith('/api/oauth/token') ? { json: { access_token: 'TESTTOKEN' } } : route(url, init));

test('search() returns the parsed body on success', async () => {
  mock = installMockFetch(withOAuth(() => ({ json: { data: [{ id: 'abc' }] } })));
  assert.deepEqual(await search('country', { limit: 1 }), { data: [{ id: 'abc' }] });
});

test('search() throws on a non-2xx response', async () => {
  mock = installMockFetch(withOAuth((url) => (url.includes('/api/search/') ? { status: 500 } : {})));
  await assert.rejects(() => search('country', {}), /admin search country failed \(HTTP 500\)/);
});

test('refreshIndexes stops when the indexer reports finish:true', async () => {
  mock = installMockFetch(withOAuth((url) => (url.includes('/indexing/') ? { json: { finish: true } } : {})));
  await refreshIndexes(['product.indexer']);
  const indexingCalls = mock.calls.filter((c) => c.url.includes('/indexing/'));
  assert.equal(indexingCalls.length, 1);
});

test('refreshIndexes does NOT loop when a numeric offset never advances (Low#3 fix)', async () => {
  // The API keeps returning {finish:false, offset:0}. Before the `next === offset` guard this spun to
  // the 1000-iteration cap; now it stops after one no-progress response.
  mock = installMockFetch(withOAuth((url) => (url.includes('/indexing/') ? { json: { finish: false, offset: 0 } } : {})));
  await refreshIndexes(['product.indexer']);
  const indexingCalls = mock.calls.filter((c) => c.url.includes('/indexing/'));
  assert.equal(indexingCalls.length, 1, 'should stop after the first no-progress page, not loop');
});

test('refreshIndexes advances through paged offsets then stops', async () => {
  let n = 0;
  mock = installMockFetch(withOAuth((url) => {
    if (!url.includes('/indexing/')) return {};
    n += 1;
    return { json: n === 1 ? { finish: false, offset: { offset: 250 } } : { finish: true } };
  }));
  await refreshIndexes(['product.indexer']);
  assert.equal(mock.calls.filter((c) => c.url.includes('/indexing/')).length, 2);
});
