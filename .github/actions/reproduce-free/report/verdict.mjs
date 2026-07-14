#!/usr/bin/env node
// Deterministic verdict from the two leg results — no agent input.
//
//   reported \ trunk   reproduced        not_reproduced
//   reproduced         live_bug          fixed_on_trunk
//   not_reproduced     regression        not_reproducible
//
// Precedence over the matrix: either leg blocked → blocked; agent edits to the provisioned shop's
// own code (shop-src-edits.txt) or inconsistent run signals → needs_human_review. Emits KEY=VALUE
// to $GITHUB_OUTPUT (and stdout): has_results, reported, trunk, verdict, unsure_reason.
import fs from 'node:fs';
import { readJson } from '../lib.mjs';

const MATRIX = {
  'reproduced/reproduced': 'live_bug',
  'reproduced/not_reproduced': 'fixed_on_trunk',
  'not_reproduced/reproduced': 'regression',
  'not_reproduced/not_reproduced': 'not_reproducible',
};

/**
 * Compacts verdict output values for safe `GITHUB_OUTPUT` emission.
 */
const oneLine = (s, max = 300) => String(s).replace(/[\r\n]+/g, ' ').replace(/\s+/g, ' ').trim().slice(0, max);

/**
 * Reads a small optional text artifact, returning '' when absent.
 */
const readText = (path) => {
  try {
    return fs.readFileSync(path, 'utf8').trim();
  } catch {
    return '';
  }
};

/**
 * Computes the deterministic issue verdict from the reported and trunk leg results.
 *
 * Shop-code edits and marker/exit-code inconsistencies override the simple status matrix so a run
 * whose signals can't be taken at face value is surfaced as `needs_human_review` instead of a
 * misleading closure recommendation.
 */
export function computeVerdict(art = process.env.ART || 'artifacts') {
  const reportedLeg = readJson(`${art}/repro-reported/result.json`, null);
  const trunkLeg = readJson(`${art}/repro-trunk/result.json`, null);
  if (!reportedLeg && !trunkLeg) {
    return { has_results: false };
  }

  const reported = reportedLeg?.status ?? 'null';
  const trunk = trunkLeg?.status ?? 'null';
  const shopEdits = readText(`${art}/repro-bundle/shop-src-edits.txt`);
  const inconsistencies = [
    ...(reportedLeg?.inconsistencies || []),
    ...(trunkLeg?.inconsistencies || []),
  ];

  let verdict;
  let unsureReason = '';
  if (reported === 'blocked' || trunk === 'blocked') {
    verdict = 'blocked';
    unsureReason = reportedLeg?.blocked_reason || trunkLeg?.blocked_reason || '';
  } else if (shopEdits) {
    verdict = 'needs_human_review';
    unsureReason = 'the agent edited the provisioned shop\'s own code, so the reported leg did not run on a pristine shop';
  } else if (inconsistencies.length) {
    verdict = 'needs_human_review';
    unsureReason = inconsistencies[0];
  } else {
    verdict = MATRIX[`${reported}/${trunk}`] || 'needs_human_review';
    if (verdict === 'needs_human_review') {
      unsureReason = `a leg produced no judgeable result (reported: ${reported}, trunk: ${trunk})`;
    }
  }

  return {
    has_results: true,
    reported,
    trunk,
    verdict,
    unsure_reason: oneLine(unsureReason),
  };
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const result = computeVerdict();
  const lines = Object.entries(result).map(([k, v]) => `${k}=${v}`);
  if (process.env.GITHUB_OUTPUT) {
    fs.appendFileSync(process.env.GITHUB_OUTPUT, `${lines.join('\n')}\n`);
  }
  for (const line of lines) {
    console.log(line);
  }
}
