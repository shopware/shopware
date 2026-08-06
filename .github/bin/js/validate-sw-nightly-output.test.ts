import { describe, it } from 'node:test';
import assert from 'node:assert/strict';

import { validateNightlyOutput } from './validate-sw-nightly-output.ts';

const validCluster = () => ({
  signature: "WriteException: [/N/type] This value should not be blank",
  root_cause: "product.type gets Required() under v6.8.0.0 in src/Core/Content/Product/ProductDefinition.php (flag block)",
  confidence: 'confirmed',
  owner_label: 'domain/inventory',
  known_cluster: true,
  flaky_or_environmental: false,
  tests: ['Shopware\\Tests\\Integration\\Core\\Content\\Product\\ProductEntityTest::testWrite'],
  evidence_quotes: ['[logs] [/0/type] This value should not be blank.'],
  related_issues: [17973],
  related_prs: [],
});

const validPayload = () => ({
  summary: 'Six failing tests collapse into one confirmed root cause and one flaky pattern.',
  clusters: [validCluster()],
});

describe('validateNightlyOutput', () => {
  it('accepts a valid payload', () => {
    assert.deepEqual(validateNightlyOutput(validPayload()), []);
  });

  it('rejects non-objects and unknown top-level fields', () => {
    assert.deepEqual(validateNightlyOutput([]), ['payload is not a JSON object']);
    const withExtra = { ...validPayload(), reroutes: [] };
    assert.ok(validateNightlyOutput(withExtra).some((v) => v.includes('unexpected field: reroutes')));
  });

  it('rejects missing and unknown cluster fields', () => {
    const cluster = validCluster() as Record<string, unknown>;
    delete cluster.owner_label;
    cluster.extra = 'x';
    const violations = validateNightlyOutput({ summary: 's', clusters: [cluster] });
    assert.ok(violations.some((v) => v.includes('missing required field: owner_label')));
    assert.ok(violations.some((v) => v.includes('unexpected field: extra')));
  });

  it('enforces enums, label catalogue, and count limits', () => {
    const violations = validateNightlyOutput({
      summary: 's',
      clusters: [{
        ...validCluster(),
        confidence: 'certain',
        owner_label: 'domain/made-up',
        tests: [],
        evidence_quotes: [],
      }],
    });
    assert.ok(violations.some((v) => v.includes('confidence invalid')));
    assert.ok(violations.some((v) => v.includes('owner_label not null and not in DOMAINS.md catalogue')));
    assert.ok(violations.some((v) => v.includes('.tests count must be 1-30')));
    assert.ok(violations.some((v) => v.includes('.evidence_quotes count must be 1-5')));
  });

  it('allows a null owner_label and an empty clusters array', () => {
    assert.deepEqual(validateNightlyOutput({ summary: 's', clusters: [{ ...validCluster(), owner_label: null }] }), []);
    assert.deepEqual(validateNightlyOutput({ summary: 'No failing tests listed in the issue.', clusters: [] }), []);
  });

  it('rejects non-integer related references', () => {
    const violations = validateNightlyOutput({
      summary: 's',
      clusters: [{ ...validCluster(), related_prs: ['#18700'] }],
    });
    assert.ok(violations.some((v) => v.includes('related_prs[0] must be a positive integer')));
  });

  it('flags secret-shaped strings anywhere in the payload', () => {
    const violations = validateNightlyOutput({
      summary: 's',
      clusters: [{
        ...validCluster(),
        root_cause: `token ghp_${'a1B2c3D4'.repeat(5)} leaked`,
      }],
    });
    assert.ok(violations.some((v) => v.includes('POSSIBLE SECRET LEAK')));
  });
});
