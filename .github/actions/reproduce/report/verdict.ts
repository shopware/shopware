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
import { readJson as readJsonOr } from '../bundle.ts';
import type { LegResult, Plan, Verdict } from '../types.ts';

const MATRIX: Record<string, Verdict> = {
  'reproduced/reproduced': 'live_bug',
  'reproduced/not_reproduced': 'fixed_on_trunk',
  'not_reproduced/reproduced': 'regression',
  'not_reproduced/not_reproduced': 'not_reproducible',
  // A missing reported leg + trunk reproduced is still a live bug: trunk went red and the comment
  // surfaces that failing test for a human to inspect.
  'null/reproduced': 'live_bug',
  // 'null/not_reproduced' is intentionally omitted. The agent only ever tunes the bundle against the
  // reported version (repro try) and never sees trunk, so a red reported leg is the ONLY evidence the
  // test actually bites. With the reported leg missing, a green trunk run can't distinguish
  // "fixed/absent" from a no-op test that passes everywhere — so it must not yield the confident
  // not_reproducible closure signal. It falls through to needs_human_review via the `|| ...` below.
};

/**
 * Reads optional JSON verdict artifacts, returning null when a leg did not upload one.
 */
const readJson = <T>(path: string): T | null => readJsonOr<T | null>(path, null);
/**
 * Compacts verdict output values for safe `GITHUB_OUTPUT` emission.
 */
const oneLine = (s: string, max = 300): string => String(s).replace(/[\r\n]+/g, ' ').replace(/\s+/g, ' ').trim().slice(0, max);

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
  const reportedLeg = readJson<LegResult>(`${art}/repro-reported/result.json`);
  const trunkLeg = readJson<LegResult>(`${art}/repro-trunk/result.json`);
  if (!reportedLeg && !trunkLeg) {
    return { has_results: false };
  }

  const plan: Partial<Plan> = readJson<Partial<Plan>>(`${art}/repro-plan/reproduction-plan.json`) || {};
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
  // If the agent patched the provisioned shop's own source (shop/src/*), the reported leg ran against
  // a modified Shopware core, so the verdict may not reflect stock behavior. Don't block — keep the
  // run and its evidence — but downgrade to needs_human_review with a clear reason. The audit
  // post-step records such edits in workspace-edits.txt (shop/src lines are prefixed `shop/`).
  let editsList = '';
  try {
    editsList = fs.readFileSync(`${art}/repro-plan/workspace-edits.txt`, 'utf8');
  } catch {
    // no edits recorded
  }
  if (/(^|\n)shop\/src\//.test(editsList) && !unsureReason) {
    unsureReason = 'the agent modified the provisioned shop under shop/src, so the reported leg ran against a patched Shopware core — the verdict may not reflect stock behavior; review the workspace edits before trusting it';
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
