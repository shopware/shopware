import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import http from 'node:http';
import type { AddressInfo } from 'node:net';
import { HttpRequestPreparer } from './request-preparer.ts';
import { HttpRequestSequenceRunner } from './request-sequence-runner.ts';
import { classifyHttpAssertions } from './assertion-classifier.ts';

// Integration slice: preparer -> sequence runner (real fetch) -> classifier, against a local server.
// Avoids the admin API by using a neutral path (no bearer/access-key resolution needed).
let server: http.Server;
let base: string;

before(async () => {
  server = http.createServer((req, res) => {
    if (req.url === '/data') {
      res.setHeader('content-type', 'application/json');
      res.setHeader('sw-context-token', 'CTX-9');
      res.end(JSON.stringify({ active: false }));
      return;
    }
    if (req.url === '/setup') {
      res.statusCode = 500;
      res.end('boom');
      return;
    }
    res.statusCode = 404;
    res.end('{}');
  });
  await new Promise((r) => server.listen(0, '127.0.0.1', r as () => void));
  base = `http://127.0.0.1:${(server.address() as AddressInfo).port}`;
  process.env.APP_URL = base;
});

after(() => { server.close(); delete process.env.APP_URL; });

const runner = () => new HttpRequestSequenceRunner(new HttpRequestPreparer());

test('a single request is fetched and classified against its body', async () => {
  const evaluation = await runner().send([{ method: 'GET', path: '/data' }], {});
  assert.equal(evaluation.code, '200');
  const r = classifyHttpAssertions([{ field: '.active', op: 'equals', expect: 'true' }], evaluation);
  assert.equal(r.status, 'reproduced'); // healthy expects true, body is false
  assert.match(evaluation.fakeScript, /curl -sS -X GET "\$APP_URL\/data"/);
});

test('a non-2xx setup request (not the last) blocks the leg', async () => {
  const evaluation = await runner().send([{ method: 'GET', path: '/setup' }, { method: 'GET', path: '/data' }], {});
  assert.match(evaluation.blocked, /setup request 1.*HTTP 500/);
  const r = classifyHttpAssertions([{ field: '.active', op: 'equals', expect: 'true' }], evaluation);
  assert.equal(r.status, 'blocked');
});

test('the sw-context-token from a response is carried forward', async () => {
  const ids: Record<string, string> = {};
  await runner().send([{ method: 'GET', path: '/data' }], ids);
  assert.equal(ids.SW_CONTEXT_TOKEN, 'CTX-9');
});

test('an off-origin path is refused (same-origin enforcement)', async () => {
  const evaluation = await runner().send([{ method: 'GET', path: '//example.com/x' }], {});
  assert.match(evaluation.blocked, /off-origin|malformed/);
});
