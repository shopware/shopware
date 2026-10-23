/**
 * Dispatches an authored bundle to its executor and writes the leg result.
 *
 * This is the single "run the test and classify" step; reset, seeding, and readiness checks live in
 * `full-run.ts` so the trusted and preview flows share ordering.
 */
import { FILES, readJson, writeJson, makeResult, blockedResult } from '../bundle.ts';
import type { Plan, LegResult } from '../types.ts';

// Executors are imported lazily so a leg only loads the module chain it actually uses. Importing the
// Playwright executor eagerly pulls in `@playwright/test` (via boilerplate/login-state.mjs), which
// crashes an http/direct leg on a runner where Playwright was — correctly — not installed.
const EXECUTORS = {
  http: () => import('../executors/http/index.ts'),
  playwright: () => import('../executors/playwright/index.ts'),
  direct: () => import('../executors/direct/index.ts'),
};

/**
 * Dispatches the authored bundle to its selected executor and writes the leg result.
 *
 * This is the single step that turns a prepared plan into `result.json`; reset, seeding, and
 * readiness checks stay in `full-run.ts` so both trusted and preview runs share ordering.
 */
export async function executeBundle({ target, out }: { target: string; out: string }) {
  // A malformed plan must become a BLOCKED leg with a reason, not an unhandled rejection that leaves
  // no result.json (which the verdict step would misread as a missing leg). validate.ts is advisory
  // (continue-on-error), so the plan can still be invalid JSON when we reach here.
  let plan: Plan;
  try {
    plan = readJson<Plan>(FILES.plan);
  } catch (err) {
    const reason = `reproduction-plan.json is not valid JSON: ${(err as Error)?.message || err}`;
    const result = blockedResult({}, target, reason);
    writeJson(out, result);
    console.error(`::error::${reason}`);
    console.log(`status=blocked  (${reason})`);
    return result;
  }

  // Fail CLOSED. A trusted verify (REPRO_ALLOW_VERIFY=1) runs the agent-authored playwright/direct
  // spec host-side, outside the awf agent sandbox, so it MUST run inside the egress-locked container.
  // The arm step only exports REPRO_SANDBOX_ARMED=1 after the image is present AND egress is dropped
  // (set -e). If arming was swallowed (continue-on-error) or skipped, the sentinel is absent and we
  // refuse to execute — a blocked leg, never an unsandboxed run. (http executes no agent code.)
  if (process.env.REPRO_ALLOW_VERIFY === '1'
    && ['playwright', 'direct'].includes(plan.executor)
    && process.env.REPRO_SANDBOX_ARMED !== '1') {
    const reason = `refusing to run the ${plan.executor} verify unsandboxed: the egress-locked container was not armed (REPRO_SANDBOX_ARMED unset — sandbox setup failed or was skipped)`;
    const result = makeResult({
      plan, target, status: 'blocked', assertion: { matched: null },
      evidence: { reporter_output: reason }, blockedReason: reason,
    });
    writeJson(out, result);
    console.error(`::error::${reason}`);
    console.log(`status=blocked  (${reason})`);
    return result;
  }

  const load = EXECUTORS[plan.executor];
  let result: LegResult;
  if (!load) {
    result = makeResult({
      plan,
      target,
      status: 'inconclusive',
      assertion: { matched: null },
      evidence: { reporter_output: `unknown executor '${plan.executor}'` },
      blockedReason: `plan.executor '${plan.executor}' is not one of playwright/http/direct`,
    });
  } else {
    // An agent-authored plan can make classify/prepare throw (e.g. an invalid regex in an http
    // assertion `matches` op or a `symptom_pattern`, neither fully validated). Catch it and write a
    // BLOCKED leg with the reason, so the leg is judged as blocked — never discarded as "incomplete"
    // for want of a result.json (mirrors full-run.ts fail() + the trunk YAML fallback).
    try {
      result = await (await load()).executor.run({ plan, target }) as LegResult;
    } catch (err) {
      result = blockedResult(plan, target, `executor '${plan.executor}' threw during run: ${(err as Error)?.message || err}`);
    }
  }
  writeJson(out, result);
  console.log(`status=${result.status}  (${result.evidence.reporter_output})`);
  return result;
}
