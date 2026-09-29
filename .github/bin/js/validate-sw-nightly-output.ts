#!/usr/bin/env node
/**
 * Post-run validation gate for the sw-nightly workflow's `nightly-triage-output.json`
 * artifact. Same two layers as validate-sw-triage-output.ts:
 *  - Shape + length: enforce the field rules the agent only had as prompt hints.
 *  - Secret-pattern scan: catches accidental or attacker-induced leakage.
 *
 * The secret patterns and the label catalogue are duplicated from
 * validate-sw-triage-output.ts on purpose (that file validates at top level and
 * cannot be imported without executing) — KEEP THEM IN SYNC, and keep the label
 * set in sync with .agents/skills/sw-triage/references/DOMAINS.md.
 *
 * No runtime dependencies; run with `node validate-sw-nightly-output.ts <file>`
 * (Node >= 22.6 strips types natively). Invoked by
 * `.github/workflows/process-sw-nightly-result.yml` after every sw-nightly run;
 * exits non-zero if anything trips, which fails the processor run.
 */

import { readFileSync, existsSync } from 'node:fs';

const CONFIDENCES = new Set<string>(['confirmed', 'plausible', 'mechanism-tbd']);

// KEEP IN SYNC with validate-sw-triage-output.ts and DOMAINS.md.
const VALID_LABELS = new Set<string>([
  'domain/framework', 'domain/inventory', 'domain/discovery', 'domain/checkout',
  'domain/crm-after-sales', 'domain/b2b', 'domain/dx-tools', 'domain/quality-ops',
  'domain/service-enablement', 'domain/ux', 'domain/customer-support', 'domain/product-ops',
  'service/data-intelligence', 'service/business-capabilities',
  'service/data-&-ai-enablement', 'service/shopping-experience', 'service/databus-nexus',
  'component/core', 'component/administration', 'component/storefront',
]);

const LIMITS = {
  summary: 2000,
  signature: 200,
  root_cause: 1000,
  clusters_max: 10,
  tests_min: 1,
  tests_max: 30,
  test_id: 300,
  evidence_quote: 500,
  evidence_quotes_min: 1,
  evidence_quotes_max: 5,
};

const CLUSTER_FIELDS = [
  'signature', 'root_cause', 'confidence', 'owner_label', 'known_cluster',
  'flaky_or_environmental', 'tests', 'evidence_quotes', 'related_issues', 'related_prs',
];

interface SecretPattern {
  name: string;
  re: RegExp;
  minEntropy?: number;
  entropyWindow?: number;
}

// KEEP IN SYNC with validate-sw-triage-output.ts (same patterns, same rationale).
const SECRET_PATTERNS: SecretPattern[] = [
  { name: 'GitHub PAT (classic)', re: /\bghp_[A-Za-z0-9]{36,}\b/ },
  { name: 'GitHub OAuth token', re: /\bgho_[A-Za-z0-9]{36,}\b/ },
  { name: 'GitHub Actions / server token', re: /\bghs_[A-Za-z0-9]{36,}\b/ },
  { name: 'GitHub user-to-server token', re: /\bghu_[A-Za-z0-9]{36,}\b/ },
  { name: 'GitHub refresh token', re: /\bghr_[A-Za-z0-9]{36,}\b/ },
  { name: 'GitHub fine-grained PAT', re: /\bgithub_pat_[A-Za-z0-9_]{60,}\b/ },
  { name: 'Anthropic API key', re: /\bsk-ant-[A-Za-z0-9_-]{32,}\b/ },
  { name: 'OpenAI API key', re: /\bsk-(?!ant-)[A-Za-z0-9]{40,}\b/ },
  { name: 'Long base64 block (potential exfil payload)', re: /[A-Za-z0-9+/]{160,}={0,2}/, minEntropy: 4.6, entropyWindow: 160 },
];

function shannonEntropy(s: string): number {
  const freq = new Map<string, number>();
  for (const ch of s) freq.set(ch, (freq.get(ch) ?? 0) + 1);
  let h = 0;
  for (const n of freq.values()) {
    const p = n / s.length;
    h -= p * Math.log2(p);
  }
  return h;
}

function entropyMatch(match: string, minEntropy: number, windowSize?: number): string | null {
  if (shannonEntropy(match) >= minEntropy) return match;
  if (windowSize === undefined || match.length <= windowSize) return null;

  for (let i = 0; i <= match.length - windowSize; i++) {
    const window = match.slice(i, i + windowSize);
    if (shannonEntropy(window) >= minEntropy) return window;
  }

  return null;
}

function redactedPreview(match: string): string {
  if (match.length <= 12) return `${'*'.repeat(match.length)} (${match.length} chars)`;
  return `${match.slice(0, 4)}…${match.slice(-4)} (${match.length} chars)`;
}

function safeSegment(key: string): string {
  return SECRET_PATTERNS.some(({ re }) => re.test(key)) ? '<redacted-key>' : key;
}

function* stringFields(value: unknown, path = '$'): Generator<[string, string]> {
  if (typeof value === 'string') {
    yield [path, value];
  } else if (Array.isArray(value)) {
    for (let i = 0; i < value.length; i++) yield* stringFields(value[i], `${path}[${i}]`);
  } else if (value && typeof value === 'object') {
    for (const [k, v] of Object.entries(value)) yield* stringFields(v, `${path}.${safeSegment(k)}`);
  }
}

function checkBoundedString(violations: string[], value: unknown, field: string, max: number): void {
  if (typeof value !== 'string' || value.length === 0) {
    violations.push(`${field} must be a non-empty string`);
  } else if (value.length > max) {
    violations.push(`${field} exceeds ${max} chars (${value.length})`);
  }
}

function checkIntegerArray(violations: string[], value: unknown, field: string): void {
  if (!Array.isArray(value)) {
    violations.push(`${field} must be an array`);
    return;
  }
  for (const [i, n] of value.entries()) {
    if (typeof n !== 'number' || !Number.isInteger(n) || n <= 0) {
      violations.push(`${field}[${i}] must be a positive integer, got ${JSON.stringify(n)}`);
    }
  }
}

export function validateNightlyOutput(parsed: unknown): string[] {
  const violations: string[] = [];

  const isObject = typeof parsed === 'object' && parsed !== null && !Array.isArray(parsed);
  if (!isObject) {
    return ['payload is not a JSON object'];
  }
  const payload = parsed as Record<string, unknown>;

  for (const k of Object.keys(payload)) {
    if (k !== 'summary' && k !== 'clusters') violations.push(`unexpected field: ${safeSegment(k)}`);
  }

  checkBoundedString(violations, payload.summary, 'summary', LIMITS.summary);

  if (!Array.isArray(payload.clusters)) {
    violations.push('clusters must be an array');
  } else {
    if (payload.clusters.length > LIMITS.clusters_max) {
      violations.push(`clusters count must be at most ${LIMITS.clusters_max}, got ${payload.clusters.length}`);
    }
    for (const [i, entry] of payload.clusters.entries()) {
      const at = `clusters[${i}]`;
      if (typeof entry !== 'object' || entry === null || Array.isArray(entry)) {
        violations.push(`${at} must be an object`);
        continue;
      }
      const cluster = entry as Record<string, unknown>;

      for (const f of CLUSTER_FIELDS) {
        if (!(f in cluster)) violations.push(`${at} missing required field: ${f}`);
      }
      for (const k of Object.keys(cluster)) {
        if (!CLUSTER_FIELDS.includes(k)) violations.push(`${at} unexpected field: ${safeSegment(k)}`);
      }

      checkBoundedString(violations, cluster.signature, `${at}.signature`, LIMITS.signature);
      checkBoundedString(violations, cluster.root_cause, `${at}.root_cause`, LIMITS.root_cause);

      if (!CONFIDENCES.has(cluster.confidence as string)) {
        violations.push(`${at}.confidence invalid: ${JSON.stringify(cluster.confidence)}`);
      }
      if (cluster.owner_label !== null && !VALID_LABELS.has(cluster.owner_label as string)) {
        violations.push(`${at}.owner_label not null and not in DOMAINS.md catalogue: ${JSON.stringify(cluster.owner_label)}`);
      }
      if (typeof cluster.known_cluster !== 'boolean') {
        violations.push(`${at}.known_cluster must be a boolean`);
      }
      if (typeof cluster.flaky_or_environmental !== 'boolean') {
        violations.push(`${at}.flaky_or_environmental must be a boolean`);
      }

      if (!Array.isArray(cluster.tests)) {
        violations.push(`${at}.tests must be an array`);
      } else {
        if (cluster.tests.length < LIMITS.tests_min || cluster.tests.length > LIMITS.tests_max) {
          violations.push(`${at}.tests count must be ${LIMITS.tests_min}-${LIMITS.tests_max}, got ${cluster.tests.length}`);
        }
        for (const [j, t] of cluster.tests.entries()) {
          if (typeof t !== 'string' || t.length === 0) violations.push(`${at}.tests[${j}] must be a non-empty string`);
          else if (t.length > LIMITS.test_id) violations.push(`${at}.tests[${j}] exceeds ${LIMITS.test_id} chars (${t.length})`);
        }
      }

      if (!Array.isArray(cluster.evidence_quotes)) {
        violations.push(`${at}.evidence_quotes must be an array`);
      } else {
        if (cluster.evidence_quotes.length < LIMITS.evidence_quotes_min || cluster.evidence_quotes.length > LIMITS.evidence_quotes_max) {
          violations.push(`${at}.evidence_quotes count must be ${LIMITS.evidence_quotes_min}-${LIMITS.evidence_quotes_max}, got ${cluster.evidence_quotes.length}`);
        }
        for (const [j, q] of cluster.evidence_quotes.entries()) {
          if (typeof q !== 'string') violations.push(`${at}.evidence_quotes[${j}] must be a string`);
          else if (q.length > LIMITS.evidence_quote) violations.push(`${at}.evidence_quotes[${j}] exceeds ${LIMITS.evidence_quote} chars (${q.length})`);
        }
      }

      checkIntegerArray(violations, cluster.related_issues, `${at}.related_issues`);
      checkIntegerArray(violations, cluster.related_prs, `${at}.related_prs`);
    }
  }

  // Secret-pattern scan over every string field — defense in depth against the
  // agent stuffing a token into any field.
  for (const [path, str] of stringFields(payload)) {
    for (const { name, re, minEntropy, entropyWindow } of SECRET_PATTERNS) {
      const matches = str.matchAll(new RegExp(re.source, re.flags.includes('g') ? re.flags : `${re.flags}g`));
      for (const m of matches) {
        const preview = minEntropy === undefined ? m[0] : entropyMatch(m[0], minEntropy, entropyWindow);
        if (preview === null) continue;
        violations.push(`POSSIBLE SECRET LEAK — ${name} at ${path}: ${redactedPreview(preview)}`);
      }
    }
  }

  return violations;
}

function main(): void {
  const file = process.argv[2];
  if (!file) {
    console.error('usage: validate-sw-nightly-output.ts <nightly-triage-output.json>');
    process.exit(2);
  }
  if (!existsSync(file)) {
    console.error(`error: artifact not found at ${file}`);
    process.exit(2);
  }

  let parsed: unknown;
  try {
    parsed = JSON.parse(readFileSync(file, 'utf8'));
  } catch (e) {
    console.error(`error: ${file} is not valid JSON: ${(e as Error).message}`);
    process.exit(2);
  }

  const violations = validateNightlyOutput(parsed);

  if (violations.length === 0) {
    console.log(`✓ ${file} passes shape + secret-scan validation`);
    process.exit(0);
  }

  console.error(`✗ ${violations.length} violation(s) in ${file}:`);
  for (const v of violations) console.error(`  - ${v}`);
  process.exit(1);
}

if (import.meta.url === `file://${process.argv[1]}`) {
  main();
}
