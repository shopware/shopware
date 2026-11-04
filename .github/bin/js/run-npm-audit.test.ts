import { describe, it } from 'node:test';
import assert from 'node:assert/strict';

import {
  AUDIT_RETRY_DELAYS_MS,
  UnusableAuditReportError,
  fetchAuditReportWithRetry,
  filterIgnored,
  parseAuditReport,
} from './run-npm-audit.ts';

const report = (vulnerabilities: Record<string, unknown> = {}) => JSON.stringify({ vulnerabilities });

const registryError = JSON.stringify({
  error: { code: 'E503', summary: 'Service Unavailable', detail: 'the advisories endpoint is down' },
});

describe('parseAuditReport', () => {
  it('returns the report when it carries a vulnerabilities section', () => {
    assert.deepEqual(parseAuditReport(report({ qs: { severity: 'moderate' } })), {
      vulnerabilities: { qs: { severity: 'moderate' } },
    });
  });

  it('accepts an empty vulnerabilities section', () => {
    assert.deepEqual(parseAuditReport(report()), { vulnerabilities: {} });
  });

  it('rejects a registry error payload with npm\'s own wording', () => {
    assert.throws(() => parseAuditReport(registryError), (err: Error) => {
      assert.ok(err instanceof UnusableAuditReportError);
      assert.match(err.message, /Service Unavailable - the advisories endpoint is down/);
      return true;
    });
  });

  it('rejects a payload without a vulnerabilities section', () => {
    assert.throws(() => parseAuditReport('{}'), UnusableAuditReportError);
  });

  it('rejects unparseable output', () => {
    assert.throws(() => parseAuditReport('<html>502</html>'), UnusableAuditReportError);
  });
});

describe('fetchAuditReportWithRetry', () => {
  const collectSleeps = () => {
    const slept: number[] = [];
    return { slept, sleep: (ms: number) => void slept.push(ms) };
  };

  it('does not retry when the first attempt succeeds', () => {
    const { slept, sleep } = collectSleeps();
    let calls = 0;
    const result = fetchAuditReportWithRetry(() => {
      calls++;
      return report();
    }, sleep);

    assert.deepEqual(result, { vulnerabilities: {} });
    assert.equal(calls, 1);
    assert.deepEqual(slept, []);
  });

  it('retries a registry failure and returns the first usable report', () => {
    const { slept, sleep } = collectSleeps();
    const outputs = [registryError, registryError, report({ qs: {} })];
    let calls = 0;
    const result = fetchAuditReportWithRetry(() => outputs[calls++]!, sleep, [1, 2, 3], () => {});

    assert.deepEqual(result, { vulnerabilities: { qs: {} } });
    assert.equal(calls, 3);
    assert.deepEqual(slept, [1, 2]);
  });

  it('fails after the last delay is used up, keeping the gate closed', () => {
    const { slept, sleep } = collectSleeps();
    let calls = 0;
    assert.throws(
      () => fetchAuditReportWithRetry(() => { calls++; return registryError; }, sleep, [1, 2], () => {}),
      /did not return a usable report after 3 attempts: Service Unavailable/,
    );
    assert.equal(calls, 3);
    assert.deepEqual(slept, [1, 2]);
  });

  it('does not retry errors that are not about an unusable report', () => {
    const { slept, sleep } = collectSleeps();
    let calls = 0;
    assert.throws(() => fetchAuditReportWithRetry(() => {
      calls++;
      throw new Error('Error running npm audit: spawn ENOENT');
    }, sleep, [1, 2], () => {}), /spawn ENOENT/);

    assert.equal(calls, 1);
    assert.deepEqual(slept, []);
  });

  it('reports each retry so a flaky registry is visible in the job log', () => {
    const warnings: string[] = [];
    const outputs = [registryError, report()];
    let calls = 0;
    fetchAuditReportWithRetry(() => outputs[calls++]!, () => {}, [1_000], (message) => void warnings.push(message));

    assert.equal(warnings.length, 1);
    assert.match(warnings[0]!, /retrying in 1s/);
  });

  it('defaults to three escalating delays', () => {
    assert.deepEqual([...AUDIT_RETRY_DELAYS_MS], [3_000, 10_000, 30_000]);
  });
});

describe('filterIgnored', () => {
  const advisory = (ghsa: string) => ({ url: `https://github.com/advisories/${ghsa}` });
  const audit = (vias: Record<string, unknown[]>) => ({
    vulnerabilities: Object.fromEntries(Object.entries(vias).map(([name, via]) => [name, { name, via }])),
  });
  const vias = (result: { vulnerabilities: Record<string, { via: unknown[] }> }) =>
    Object.fromEntries(Object.entries(result.vulnerabilities).map(([name, pkg]) => [name, pkg.via]));
  const run = (input: ReturnType<typeof audit>, ignoredGHSAs: string[]) => {
    filterIgnored(input as never, new Set(ignoredGHSAs), new Set());
    return vias(input);
  };

  it('clears the packages that only depended on an ignored advisory', () => {
    const result = run(audit({
      braces: [advisory('GHSA-aaaa-aaaa-aaaa')],
      micromatch: ['braces'],
      jest: ['micromatch'],
    }), ['GHSA-aaaa-aaaa-aaaa']);

    assert.deepEqual(result, { braces: [], micromatch: [], jest: [] });
  });

  it('clears packages that reference each other once their only advisory is ignored', () => {
    const result = run(audit({
      '@vue/server-renderer': [advisory('GHSA-g2v6-rqmx-r4w6'), 'vue'],
      vue: ['@vue/server-renderer'],
      '@tanstack/vue-virtual': ['vue'],
    }), ['GHSA-g2v6-rqmx-r4w6']);

    assert.deepEqual(result, { '@vue/server-renderer': [], vue: [], '@tanstack/vue-virtual': [] });
  });

  it('keeps a cycle vulnerable while one of its packages has an advisory that is not ignored', () => {
    const remaining = advisory('GHSA-bbbb-bbbb-bbbb');
    const result = run(audit({
      '@vue/server-renderer': [advisory('GHSA-g2v6-rqmx-r4w6'), 'vue'],
      vue: ['@vue/server-renderer', '@vue/shared'],
      '@vue/shared': [remaining],
    }), ['GHSA-g2v6-rqmx-r4w6']);

    assert.deepEqual(result, {
      '@vue/server-renderer': ['vue'],
      vue: ['@vue/server-renderer', '@vue/shared'],
      '@vue/shared': [remaining],
    });
  });

  it('drops references to packages that are not part of the report', () => {
    const remaining = advisory('GHSA-cccc-cccc-cccc');
    const result = run(audit({ qs: [remaining, 'missing-package'] }), []);

    assert.deepEqual(result, { qs: [remaining] });
  });
});
