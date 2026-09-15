import { test, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { installMockFetch } from '../../tests/helpers/mock-fetch.ts';
import type { MockFetchHandle, RouteFn } from '../../tests/helpers/mock-fetch.ts';
import { schemaCommand, searchCommand, versionCommand } from './inspect.ts';

// inspect's commands are thin wrappers over the Admin API client, so every test runs against a
// stubbed global fetch (like admin-api.test.ts). token() caches its bearer at module scope, so an
// OAuth route is always provided; console + process.exit are captured so we can assert on the
// printed report and the exit codes without ending the test process.
let mock: MockFetchHandle | undefined;
let logs: string[];
let errs: string[];
let origLog: typeof console.log;
let origErr: typeof console.error;
let origExit: typeof process.exit;
let exits: Array<number | string | null | undefined>;

// A sentinel thrown by the stubbed process.exit so control does not fall through into code that
// assumes the process has already gone away. Tests recover it via assert.rejects / try-catch.
class ExitError extends Error {
  code: number | string | null | undefined;

  constructor(code: number | string | null | undefined) {
    super(`process.exit(${code})`);
    this.code = code;
  }
}

beforeEach(() => {
  process.env.APP_URL = 'http://shop.test';
  logs = [];
  errs = [];
  exits = [];
  origLog = console.log;
  origErr = console.error;
  origExit = process.exit;
  console.log = (...a) => logs.push(a.join(' '));
  console.error = (...a) => errs.push(a.join(' '));
  process.exit = (code) => { exits.push(code); throw new ExitError(code); };
});

afterEach(() => {
  mock?.restore();
  console.log = origLog;
  console.error = origErr;
  process.exit = origExit;
});

const withOAuth = (route: RouteFn): RouteFn => (url, init) =>
  (url.endsWith('/api/oauth/token') ? { json: { access_token: 'TESTTOKEN' } } : route(url, init));

// ---------------------------------------------------------------------------
// schema command
// ---------------------------------------------------------------------------

test('schemaCommand() with no entity prints every entity name, sorted', async () => {
  mock = installMockFetch(withOAuth((url) =>
    (url.includes('/_info/entity-schema.json')
      ? { json: { product: {}, category: {}, tax: {} } }
      : {})));
  await schemaCommand();
  assert.equal(logs.length, 1);
  assert.equal(logs[0], 'category\nproduct\ntax');
});

test('schemaCommand(entity) prints the trimmed field schema for that entity', async () => {
  const def = {
    properties: {
      id: { type: 'uuid', flags: { primary_key: true, required: true }, description: 'the id' },
      name: { type: 'string', flags: { translatable: true } },
      tax: {
        type: 'association',
        relation: 'many_to_one',
        entity: 'tax',
        localField: 'taxId',
        flags: {},
      },
      // read_protected noise that trimEntity must not surface as a top-level key
      secret: { type: 'string', flags: { read_protected: ['SomeSource'] } },
    },
  };
  mock = installMockFetch(withOAuth((url) =>
    (url.includes('/_info/entity-schema.json') ? { json: { product: def } } : {})));

  await schemaCommand('product');
  assert.equal(logs.length, 1);
  const out = JSON.parse(logs[0]);
  assert.equal(out.entity, 'product');
  assert.deepEqual(out.properties.id, { type: 'uuid', required: true, primaryKey: true, description: 'the id' });
  assert.deepEqual(out.properties.name, { type: 'string', translatable: true });
  assert.deepEqual(out.properties.tax, {
    type: 'association', relation: 'many_to_one', entity: 'tax', localField: 'taxId',
  });
  // read_protected flag arrays are dropped; only the plain type survives
  assert.deepEqual(out.properties.secret, { type: 'string' });
});

test('schemaCommand(entity) exits 1 for an unknown entity', async () => {
  mock = installMockFetch(withOAuth((url) =>
    (url.includes('/_info/entity-schema.json') ? { json: { product: {} } } : {})));
  await assert.rejects(() => schemaCommand('does-not-exist'), ExitError);
  assert.deepEqual(exits, [1]);
  assert.match(errs.join('\n'), /unknown entity 'does-not-exist'/);
});

// ---------------------------------------------------------------------------
// search command
// ---------------------------------------------------------------------------

test('searchCommand() without an entity prints usage and exits 2', async () => {
  mock = installMockFetch(withOAuth(() => ({})));
  await assert.rejects(() => searchCommand(), ExitError);
  assert.deepEqual(exits, [2]);
  assert.match(errs.join('\n'), /usage: repro search <entity>/);
});

test('searchCommand(entity) defaults criteria to {limit:10} and prints total + data', async () => {
  mock = installMockFetch(withOAuth((url) =>
    (url.includes('/api/search/tax') ? { json: { total: 1, data: [{ id: 'tax-1' }] } } : {})));
  await searchCommand('tax');

  // the default criteria reached the Admin API unchanged
  const searchCall = mock.calls.find((c) => c.url.includes('/api/search/tax'));
  assert.deepEqual(JSON.parse(searchCall!.init.body as string), { limit: 10 });

  assert.equal(logs.length, 1);
  assert.deepEqual(JSON.parse(logs[0]), { total: 1, data: [{ id: 'tax-1' }] });
});

test('searchCommand(entity, criteria) forwards the parsed criteria JSON', async () => {
  mock = installMockFetch(withOAuth((url) =>
    (url.includes('/api/search/product') ? { json: { total: 0, data: [] } } : {})));
  await searchCommand('product', '{"limit":3,"filter":[{"type":"equals","field":"active","value":true}]}');

  const searchCall = mock.calls.find((c) => c.url.includes('/api/search/product'));
  assert.deepEqual(JSON.parse(searchCall!.init.body as string), {
    limit: 3, filter: [{ type: 'equals', field: 'active', value: true }],
  });
});

test('searchCommand() falls back to data.length when the response omits total', async () => {
  mock = installMockFetch(withOAuth((url) =>
    (url.includes('/api/search/currency') ? { json: { data: [{ id: 'a' }, { id: 'b' }] } } : {})));
  await searchCommand('currency');
  assert.equal(JSON.parse(logs[0]).total, 2);
});

test('searchCommand() exits 2 on invalid criteria JSON without hitting the API', async () => {
  mock = installMockFetch(withOAuth(() => ({})));
  await assert.rejects(() => searchCommand('tax', '{not json'), ExitError);
  assert.deepEqual(exits, [2]);
  assert.match(errs.join('\n'), /criteria must be valid JSON/);
  assert.equal(mock.calls.filter((c) => c.url.includes('/api/search/')).length, 0);
});

// ---------------------------------------------------------------------------
// version command
// ---------------------------------------------------------------------------

test('versionCommand() prints the live version', async () => {
  mock = installMockFetch(withOAuth((url) =>
    (url.includes('/_info/version') ? { json: { version: '6.7.2.0' } } : {})));
  await versionCommand();
  assert.deepEqual(logs, ['6.7.2.0']);
  assert.equal(errs.length, 0);
});

test('versionCommand() prints (unknown) when the instance reports no version', async () => {
  mock = installMockFetch(withOAuth((url) =>
    (url.includes('/_info/version') ? { json: {} } : {})));
  await versionCommand();
  assert.deepEqual(logs, ['(unknown)']);
});

test('versionCommand(expected) warns when the live version differs', async () => {
  mock = installMockFetch(withOAuth((url) =>
    (url.includes('/_info/version') ? { json: { version: '6.7.2.0' } } : {})));
  await versionCommand('6.6.0.0');
  assert.deepEqual(logs, ['6.7.2.0']);
  assert.match(errs.join('\n'), /::warning::live instance is 6\.7\.2\.0 but the issue reports 6\.6\.0\.0/);
});

test('versionCommand(expected) stays quiet when the versions match', async () => {
  mock = installMockFetch(withOAuth((url) =>
    (url.includes('/_info/version') ? { json: { version: '6.7.2.0' } } : {})));
  await versionCommand('6.7.2.0');
  assert.equal(errs.length, 0);
});
