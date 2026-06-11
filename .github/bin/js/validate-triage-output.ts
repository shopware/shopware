#!/usr/bin/env node
/**
 * Post-run validation gate for the triage workflow's `triage-output.json` artifact.
 *
 * Two layers:
 *  - Shape + length: enforce the field rules the agent only had as prompt hints
 *    (max chars per field, enum values, count limits). A successful prompt
 *    injection can violate these without anything noticing — we make them hard.
 *  - Secret-pattern scan: catches accidental or attacker-induced leakage of
 *    GitHub PATs, Anthropic keys, OAuth tokens, and long base64 blocks that look
 *    like exfil payloads.
 *
 * No runtime dependencies. TypeScript types are erased at runtime — run with
 * `node validate-triage-output.ts` (Node >= 22.6 strips types natively) in CI,
 * or `npx esno validate-triage-output.ts` locally. Invoked by
 * `.github/workflows/process-triage-result.yml` after every triage run; exits
 * non-zero if anything trips, which fails the processor run.
 *
 * Usage:
 *   node validate-triage-output.ts <path-to-triage-output.json>
 */

import { readFileSync, existsSync } from 'node:fs';

const FILE = process.argv[2];
if (!FILE) {
  console.error('usage: validate-triage-output.ts <triage-output.json>');
  process.exit(2);
}
if (!existsSync(FILE)) {
  console.error(`error: artifact not found at ${FILE}`);
  process.exit(2);
}

const REQUIRED_FIELDS = [
  'disposition', 'severity', 'suggested_labels', 'confidence',
  'reasoning', 'evidence_quotes', 'duplicate_of', 'missing_template_fields',
  'affected_paths', 'related_issues', 'related_prs',
  'recent_commits_in_area', 'change_size_estimate',
];

const DISPOSITIONS = new Set<string>(['valid-bug', 'duplicate', 'needs-info', 'not-a-bug', 'feature-request']);
const SEVERITIES = new Set<string>(['low', 'medium', 'high', 'critical']);
const CHANGE_SIZES = new Set<string>(['quick-fix', 'small', 'medium', 'large', 'unknown']);

// The closed label catalogue. KEEP IN SYNC with the canonical list in
// .claude/skills/triage/references/DOMAINS.md — when a label is added/removed
// there, mirror it here (and vice-versa). Kept as a hardcoded set on purpose:
// parsing the prose doc at runtime would make this gate fragile and non-hermetic.
const COMPONENT_LABELS = new Set<string>(['component/core', 'component/administration', 'component/storefront']);
const VALID_LABELS = new Set<string>([
  'domain/framework', 'domain/inventory', 'domain/discovery', 'domain/checkout',
  'domain/crm-after-sales', 'domain/b2b', 'domain/dx-tools', 'domain/quality-ops',
  'domain/service-enablement', 'domain/ux', 'domain/customer-support', 'domain/product-ops',
  'service/data-intelligence', 'service/business-capabilities',
  'service/data-&-ai-enablement', 'service/shopping-experience', 'service/databus-nexus',
  ...COMPONENT_LABELS,
]);

const LIMITS = {
  reasoning: 2000,
  evidence_quote: 500,
  evidence_quotes_min: 1,
  evidence_quotes_max: 5,
  recent_commit: 200,
  labels_min: 1,
  labels_max: 2,
};

interface SecretPattern {
  name: string;
  re: RegExp;
  /** When set, a regex match only counts if its Shannon entropy clears this bar. */
  minEntropy?: number;
  /** When set, also scan sliding windows of this size within a match for a high-entropy run. */
  entropyWindow?: number;
}

// Catastrophic-leakage patterns. A match fails this post-run validation (red check on
// the triage run) — it does NOT abort the upload: by the time this runs the artifact is
// already stored, so this is the deterministic backstop. Upload-time blocking is gh-aw's
// threat-detection job (configured via safe-outputs.threat-detection in triage.md), which
// runs before the upload and is aligned to these same patterns.
// GitHub token prefixes per https://github.blog/2021-04-05-behind-githubs-new-authentication-token-formats/
const SECRET_PATTERNS: SecretPattern[] = [
  { name: 'GitHub PAT (classic)', re: /\bghp_[A-Za-z0-9]{36,}\b/ },
  { name: 'GitHub OAuth token', re: /\bgho_[A-Za-z0-9]{36,}\b/ },
  { name: 'GitHub Actions / server token', re: /\bghs_[A-Za-z0-9]{36,}\b/ },
  { name: 'GitHub user-to-server token', re: /\bghu_[A-Za-z0-9]{36,}\b/ },
  { name: 'GitHub refresh token', re: /\bghr_[A-Za-z0-9]{36,}\b/ },
  { name: 'GitHub fine-grained PAT', re: /\bgithub_pat_[A-Za-z0-9_]{60,}\b/ },
  { name: 'Anthropic API key', re: /\bsk-ant-[A-Za-z0-9_-]{32,}\b/ },
  { name: 'OpenAI API key', re: /\bsk-(?!ant-)[A-Za-z0-9]{40,}\b/ },
  // Long base64 block — heuristic for arbitrary binary exfil. Length-bounded so
  // commit SHAs (40 hex) and JWT segments (~80–110) stay under it, and entropy-
  // gated (minEntropy) so long-but-structured content — minified code, repeated-
  // token strings quoted as evidence — scores below the bar. Only high-entropy
  // (near-random) blocks match; the redacted preview in the scan output lets a
  // reviewer judge a hit at a glance.
  { name: 'Long base64 block (potential exfil payload)', re: /[A-Za-z0-9+/]{160,}={0,2}/, minEntropy: 4.6, entropyWindow: 160 },
];

// Shannon entropy in bits/char. Uniformly-random base64 approaches log2(64)=6;
// structured text (repeated tokens, minified code) sits well below.
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

// Show enough of a match to recognise it, never enough to leak it.
function redactedPreview(match: string): string {
  if (match.length <= 12) return `${'*'.repeat(match.length)} (${match.length} chars)`;
  return `${match.slice(0, 4)}…${match.slice(-4)} (${match.length} chars)`;
}

// A secret can appear as an object KEY, not only a value. Mask any path segment
// that itself matches a secret pattern, so a violation message can never echo a
// key verbatim. (Patterns are non-global → .test() is stateless and safe here.)
function safeSegment(key: string): string {
  return SECRET_PATTERNS.some(({ re }) => re.test(key)) ? '<redacted-key>' : key;
}

// Yield [jsonPath, value] for every string anywhere in the payload (recurses
// objects + arrays) so the scan can report which field tripped.
function* stringFields(value: unknown, path = '$'): Generator<[string, string]> {
  if (typeof value === 'string') {
    yield [path, value];
  } else if (Array.isArray(value)) {
    for (let i = 0; i < value.length; i++) yield* stringFields(value[i], `${path}[${i}]`);
  } else if (value && typeof value === 'object') {
    for (const [k, v] of Object.entries(value)) yield* stringFields(v, `${path}.${safeSegment(k)}`);
  }
}

const violations: string[] = [];

let parsed: unknown;
try {
  parsed = JSON.parse(readFileSync(FILE, 'utf8'));
} catch (e) {
  console.error(`error: ${FILE} is not valid JSON: ${(e as Error).message}`);
  process.exit(2);
}

const isObject = typeof parsed === 'object' && parsed !== null && !Array.isArray(parsed);
if (!isObject) {
  violations.push('payload is not a JSON object');
}
// Inspect as an untrusted bag of unknowns; runtime guards below narrow each field.
const payload: Record<string, unknown> = isObject ? (parsed as Record<string, unknown>) : {};

for (const f of REQUIRED_FIELDS) {
  if (!(f in payload)) violations.push(`missing required field: ${f}`);
}

// Strict shape: the contract is a flat object with exactly the known keys.
// Reject anything else — an unexpected key (possibly a secret used AS the key,
// hence the redaction) or a nested object, which would smuggle attacker-
// controlled keys past the value-only secret scan.
{
  const allowedKeys = new Set(REQUIRED_FIELDS);
  for (const [k, v] of Object.entries(payload)) {
    if (!allowedKeys.has(k)) violations.push(`unexpected field: ${safeSegment(k)}`);
    if (v && typeof v === 'object' && !Array.isArray(v)) {
      violations.push(`field ${safeSegment(k)} must not be a nested object`);
    }
  }
}

if (payload.disposition !== undefined && !DISPOSITIONS.has(payload.disposition as string)) {
  violations.push(`invalid disposition: ${JSON.stringify(payload.disposition)}`);
}
if (payload.severity !== undefined && !SEVERITIES.has(payload.severity as string)) {
  violations.push(`invalid severity: ${JSON.stringify(payload.severity)}`);
}
if (payload.change_size_estimate !== undefined && !CHANGE_SIZES.has(payload.change_size_estimate as string)) {
  violations.push(`invalid change_size_estimate: ${JSON.stringify(payload.change_size_estimate)}`);
}

if (typeof payload.confidence !== 'number' || payload.confidence < 0 || payload.confidence > 1) {
  violations.push(`confidence must be a number in [0,1], got ${JSON.stringify(payload.confidence)}`);
}

if (typeof payload.reasoning !== 'string') {
  violations.push('reasoning must be a string');
} else if (payload.reasoning.length > LIMITS.reasoning) {
  violations.push(`reasoning exceeds ${LIMITS.reasoning} chars (${payload.reasoning.length})`);
}

if (!Array.isArray(payload.evidence_quotes)) {
  violations.push('evidence_quotes must be an array');
} else {
  if (payload.evidence_quotes.length < LIMITS.evidence_quotes_min || payload.evidence_quotes.length > LIMITS.evidence_quotes_max) {
    violations.push(`evidence_quotes count must be ${LIMITS.evidence_quotes_min}-${LIMITS.evidence_quotes_max}, got ${payload.evidence_quotes.length}`);
  }
  for (const [i, q] of payload.evidence_quotes.entries()) {
    if (typeof q !== 'string') violations.push(`evidence_quotes[${i}] must be a string`);
    else if (q.length > LIMITS.evidence_quote) violations.push(`evidence_quotes[${i}] exceeds ${LIMITS.evidence_quote} chars (${q.length})`);
  }
}

if (!Array.isArray(payload.suggested_labels)) {
  violations.push('suggested_labels must be an array');
} else {
  const labels = payload.suggested_labels;
  if (labels.length < LIMITS.labels_min || labels.length > LIMITS.labels_max) {
    violations.push(`suggested_labels count must be ${LIMITS.labels_min}-${LIMITS.labels_max}, got ${labels.length}`);
  }
  for (const [i, l] of labels.entries()) {
    if (typeof l !== 'string') violations.push(`suggested_labels[${i}] must be a string`);
    else if (!VALID_LABELS.has(l)) violations.push(`suggested_labels[${i}] not in DOMAINS.md catalogue: ${JSON.stringify(l)}`);
  }
  // domain/framework requires an accompanying component/* label (DOMAINS.md "Required second label").
  if (labels.includes('domain/framework') && !labels.some((l: unknown) => typeof l === 'string' && COMPONENT_LABELS.has(l))) {
    violations.push('domain/framework requires a component/* label (component/core|administration|storefront)');
  }
}

if (payload.recent_commits_in_area !== undefined) {
  if (!Array.isArray(payload.recent_commits_in_area)) {
    violations.push('recent_commits_in_area must be an array');
  } else {
    for (const [i, c] of payload.recent_commits_in_area.entries()) {
      if (typeof c !== 'string') violations.push(`recent_commits_in_area[${i}] must be a string`);
      else if (c.length > LIMITS.recent_commit) violations.push(`recent_commits_in_area[${i}] exceeds ${LIMITS.recent_commit} chars (${c.length})`);
    }
  }
}

if (payload.duplicate_of !== null && typeof payload.duplicate_of !== 'number') {
  violations.push(`duplicate_of must be a number or null, got ${typeof payload.duplicate_of}`);
}

// Secret-pattern scan over every string field — defense in depth against the
// agent stuffing a token into any field. Reports the field path + a redacted
// preview so a human can resolve a hit (real leak vs. harmless quoted blob) at
// a glance. The base64 heuristic is entropy-gated; the prefix patterns are not.
for (const [path, str] of stringFields(payload)) {
  for (const { name, re, minEntropy, entropyWindow } of SECRET_PATTERNS) {
    // Build a local global clone of the pattern (matchAll needs the global flag)
    // so the shared SECRET_PATTERNS stay non-global — that keeps the .test() in
    // safeSegment stateless. Each match is entropy-checked on its own, so a
    // low-entropy run in a field cannot mask a high-entropy secret elsewhere in it.
    const matches = str.matchAll(new RegExp(re.source, re.flags.includes('g') ? re.flags : `${re.flags}g`));
    for (const m of matches) {
      const preview = minEntropy === undefined ? m[0] : entropyMatch(m[0], minEntropy, entropyWindow);
      if (preview === null) continue;
      violations.push(`POSSIBLE SECRET LEAK — ${name} at ${path}: ${redactedPreview(preview)}`);
    }
  }
}

if (violations.length === 0) {
  console.log(`✓ ${FILE} passes shape + secret-scan validation`);
  process.exit(0);
}

console.error(`✗ ${violations.length} violation(s) in ${FILE}:`);
for (const v of violations) console.error(`  - ${v}`);
process.exit(1);
