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
 * Pure node, no dependencies. Run by `.github/workflows/validate-triage-output.yml`
 * after every triage run; exits non-zero if anything trips, which fails the run.
 *
 * Usage:
 *   node validate-triage-output.mjs <path-to-triage-output.json>
 */

import { readFileSync, existsSync } from 'node:fs';

const FILE = process.argv[2];
if (!FILE) {
  console.error('usage: validate-triage-output.mjs <triage-output.json>');
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

const DISPOSITIONS = new Set(['valid-bug', 'duplicate', 'needs-info', 'not-a-bug', 'feature-request']);
const SEVERITIES = new Set(['low', 'medium', 'high', 'critical']);
const CHANGE_SIZES = new Set(['quick-fix', 'small', 'medium', 'large', 'unknown']);

const LIMITS = {
  reasoning: 2000,
  evidence_quote: 500,
  evidence_quotes_min: 1,
  evidence_quotes_max: 5,
  recent_commit: 200,
  labels_min: 1,
  labels_max: 2,
};

// Catastrophic-leakage patterns. Each match aborts the upload.
// GitHub token prefixes per https://github.blog/2021-04-05-behind-githubs-new-authentication-token-formats/
const SECRET_PATTERNS = [
  { name: 'GitHub PAT (classic)', re: /\bghp_[A-Za-z0-9]{36,}\b/ },
  { name: 'GitHub OAuth token', re: /\bgho_[A-Za-z0-9]{36,}\b/ },
  { name: 'GitHub Actions / server token', re: /\bghs_[A-Za-z0-9]{36,}\b/ },
  { name: 'GitHub user-to-server token', re: /\bghu_[A-Za-z0-9]{36,}\b/ },
  { name: 'GitHub refresh token', re: /\bghr_[A-Za-z0-9]{36,}\b/ },
  { name: 'GitHub fine-grained PAT', re: /\bgithub_pat_[A-Za-z0-9_]{60,}\b/ },
  { name: 'Anthropic API key', re: /\bsk-ant-[A-Za-z0-9_-]{32,}\b/ },
  { name: 'OpenAI API key', re: /\bsk-(?!ant-)[A-Za-z0-9]{40,}\b/ },
  // Long base64 block — heuristic for arbitrary binary exfil. Tuned so commit
  // SHAs (40 hex), JWT segments (~80–110), and short hashes don't trip.
  { name: 'Long base64 block (potential exfil payload)', re: /[A-Za-z0-9+/]{160,}={0,2}/ },
];

const violations = [];

let payload;
try {
  payload = JSON.parse(readFileSync(FILE, 'utf8'));
} catch (e) {
  console.error(`error: ${FILE} is not valid JSON: ${e.message}`);
  process.exit(2);
}

if (typeof payload !== 'object' || payload === null || Array.isArray(payload)) {
  violations.push('payload is not a JSON object');
}

for (const f of REQUIRED_FIELDS) {
  if (!(f in payload)) violations.push(`missing required field: ${f}`);
}

if (payload.disposition !== undefined && !DISPOSITIONS.has(payload.disposition)) {
  violations.push(`invalid disposition: ${JSON.stringify(payload.disposition)}`);
}
if (payload.severity !== undefined && !SEVERITIES.has(payload.severity)) {
  violations.push(`invalid severity: ${JSON.stringify(payload.severity)}`);
}
if (payload.change_size_estimate !== undefined && !CHANGE_SIZES.has(payload.change_size_estimate)) {
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
} else if (payload.suggested_labels.length < LIMITS.labels_min || payload.suggested_labels.length > LIMITS.labels_max) {
  violations.push(`suggested_labels count must be ${LIMITS.labels_min}-${LIMITS.labels_max}, got ${payload.suggested_labels.length}`);
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

// Secret-pattern scan across the whole serialized payload — defense in depth
// against the agent stuffing a token into any string field.
const allText = JSON.stringify(payload);
for (const { name, re } of SECRET_PATTERNS) {
  if (re.test(allText)) {
    violations.push(`POSSIBLE SECRET LEAK — pattern matched: ${name}`);
  }
}

if (violations.length === 0) {
  console.log(`✓ ${FILE} passes shape + secret-scan validation`);
  process.exit(0);
}

console.error(`✗ ${violations.length} violation(s) in ${FILE}:`);
for (const v of violations) console.error(`  - ${v}`);
process.exit(1);
