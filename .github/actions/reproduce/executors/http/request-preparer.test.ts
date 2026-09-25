import { test } from 'node:test';
import assert from 'node:assert/strict';
import { HttpRequestPreparer } from './request-preparer.ts';

const prep = new HttpRequestPreparer();

test('a store-api path gets the sales-channel key, not an admin bearer', async () => {
  const r = await prep.prepare({ method: 'GET', path: '/store-api/product' }, { SW_ACCESS_KEY: 'KEY123' });
  assert.equal(r.headers['sw-access-key'], 'KEY123');
  assert.equal(r.headers.Authorization, undefined);
  assert.equal(r.headers.Accept, 'application/json');
});

test('agent-authored auth/session headers are dropped (executor owns them)', async () => {
  const r = await prep.prepare({
    method: 'GET',
    path: '/store-api/x',
    headers: { Authorization: 'Bearer AGENT', cookie: 'session=1', 'X-Custom': 'keep' },
  }, { SW_ACCESS_KEY: 'KEY' });
  assert.equal(r.headers.Authorization, undefined);
  assert.equal(r.headers.cookie, undefined);
  assert.equal(r.headers['X-Custom'], 'keep');
});

test('placeholders in headers and body are filled from ids', async () => {
  const r = await prep.prepare(
    { method: 'POST', path: '/store-api/x', body: '{"lang":"{{LANGUAGE}}"}', headers: { 'sw-language-id': '{{LANGUAGE}}' } },
    { SW_ACCESS_KEY: 'K', LANGUAGE: 'L1' },
  );
  assert.equal(r.headers['sw-language-id'], 'L1');
  assert.equal(r.body, '{"lang":"L1"}');
});

test('a body without an explicit content-type defaults to application/json', async () => {
  const r = await prep.prepare({ method: 'POST', path: '/store-api/x', body: '{}' }, { SW_ACCESS_KEY: 'K' });
  assert.equal(r.headers['Content-Type'], 'application/json');
});

test('an empty authored header value is preserved (header-handling bugs)', async () => {
  const r = await prep.prepare({ method: 'GET', path: '/store-api/x', headers: { 'sw-language-id': '' } }, { SW_ACCESS_KEY: 'K' });
  assert.equal(r.headers['sw-language-id'], '');
});

test('path classification helpers', () => {
  assert.equal(prep.isStorePath('/store-api/x'), true);
  assert.equal(prep.isAdminPath('/api/x'), true);
  assert.equal(prep.isExecutorOwnedHeader('Authorization'), true);
  assert.equal(prep.isExecutorOwnedHeader('x-custom'), false);
});
