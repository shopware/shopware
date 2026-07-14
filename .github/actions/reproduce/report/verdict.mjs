#!/usr/bin/env node
// Deterministic verdict from the two leg results + the plan's confidence. No agent input.
//
//   reported \ trunk   reproduced        not_reproduced
//   reproduced         live_bug          fixed_on_trunk
//   not_reproduced     regression        not_reproducible
//
// Precedence over the matrix: either leg blocked → blocked; an unreliable plan (blocked_reason or
// confidence < 0.7) or an inconclusive leg → needs_human_review. Emits KEY=VALUE to $GITHUB_OUTPUT
// (and stdout): has_results, reported, trunk, verdict, unsure_reason, fix_candidate.
import fs from 'node:fs';

const MATRIX = {
  'reproduced/reproduced': 'live_bug',
  'reproduced/not_reproduced': 'fixed_on_trunk',
  'not_reproduced/reproduced': 'regression',
  'not_reproduced/not_reproduced': 'not_reproducible',
  'null/reproduced': 'live_bug',
  'null/not_reproduced': 'not_reproducible',
};

/**
 * Reads optional JSON verdict artifacts, returning null when a leg did not upload one.
 */
const readJson = (path) => {
  try {
    return JSON.parse(fs.readFileSync(path, 'utf8'));
  } catch {
    return null;
  }
};
/**
 * Compacts verdict output values for safe `GITHUB_OUTPUT` emission.
 */
const oneLine = (s, max = 300) => String(s).replace(/[\r\n]+/g, ' ').replace(/\s+/g, ' ').trim().slice(0, max);

/**
 * Computes the deterministic issue verdict from the reported and trunk leg results.
 *
 * Agent confidence and blocked/inconclusive legs override the simple status matrix so uncertain
 * evidence is surfaced as `needs_human_review` instead of a misleading closure recommendation.
 *
 * @returns The reported/trunk leg statuses, final verdict, uncertainty reason, and fix-candidate
 * hint used for GitHub output and issue-comment rendering.
 */
export function computeVerdict(art = process.env.ART || 'artifacts') {
  const reportedLeg = readJson(`${art}/repro-reported/result.json`);
  const trunkLeg = readJson(`${art}/repro-trunk/result.json`);
  if (!reportedLeg && !trunkLeg) {
    return { has_results: false };
  }

  const plan = readJson(`${art}/repro-plan/reproduction-plan.json`) || {};
  const reported = reportedLeg?.status ?? 'null';
  const trunk = trunkLeg?.status ?? 'null';

  // A missing OR malformed confidence conservatively routes to needs_human_review: default to 0, not
  // 1. `?? 0` alone wouldn't catch a non-number (e.g. "high" → NaN, and `NaN < 0.7` is false → falsely
  // trusted), so require an actual number and treat anything else as 0 (fully untrusted).
  const confidence = typeof plan.confidence === 'number' ? plan.confidence : 0;
  const blockedReason = plan.blocked_reason || '';
  let unsureReason = '';
  if (blockedReason && blockedReason !== 'null') {
    unsureReason = blockedReason;
  }
  if (confidence < 0.7 && !unsureReason) {
    unsureReason = plan.agent_explanation
      || `analysis confidence ${confidence} is below the 0.7 trust threshold, so the repro may not faithfully match the report`;
  }
  const unsure = Boolean(unsureReason);

  let verdict = 'needs_human_review';
  if (reported === 'blocked' || trunk === 'blocked') {
    verdict = 'blocked';
  } else if (unsure) {
    verdict = 'needs_human_review';
  } else if (reported === 'inconclusive' || trunk === 'inconclusive') {
    verdict = 'needs_human_review';
  } else {
    verdict = MATRIX[`${reported}/${trunk}`] || 'needs_human_review';
  }

  // If it's needs_human_review only because a leg was inconclusive, surface that leg's reason.
  if (verdict === 'needs_human_review' && !unsureReason) {
    const leg = [reportedLeg, trunkLeg].find((l) => l?.status === 'inconclusive');
    if (leg) {
      unsureReason = leg.blocked_reason
        || leg.evidence?.reporter_output
        || 'a leg was inconclusive — the symptom could not be judged';
    }
  }

  return {
    has_results: true,
    reported,
    trunk,
    verdict,
    unsure_reason: oneLine(unsureReason),
    fix_candidate: oneLine(plan.derived_from || ''),
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
