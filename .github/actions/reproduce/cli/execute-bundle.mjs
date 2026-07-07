/**
 * Dispatches an authored bundle to its executor and writes the leg result.
 *
 * This is the single "run the test and classify" step; reset, seeding, and readiness checks live in
 * `full-run.mjs` so the trusted and preview flows share ordering.
 */
import { FILES, readJson, writeJson, makeResult } from '../bundle.mjs';
import { executor as httpExecutor } from '../executors/http/index.mjs';
import { executor as playwrightExecutor } from '../executors/playwright/index.mjs';
import { executor as directExecutor } from '../executors/direct/index.mjs';

const EXECUTORS = {
  http: httpExecutor,
  playwright: playwrightExecutor,
  direct: directExecutor,
};

/**
 * Dispatches the authored bundle to its selected executor and writes the leg result.
 *
 * This is the single step that turns a prepared plan into `result.json`; reset, seeding, and
 * readiness checks stay in `full-run.mjs` so both trusted and preview runs share ordering.
 */
export async function executeBundle({ target, out }) {
  const plan = readJson(FILES.plan);
  const executor = EXECUTORS[plan.executor];
  const result = executor ? await executor.run({ plan, target }) : makeResult({
    plan,
    target,
    status: 'inconclusive',
    assertion: { matched: null },
    evidence: { reporter_output: `unknown executor '${plan.executor}'` },
    blockedReason: `plan.executor '${plan.executor}' is not one of playwright/http/direct`,
  });
  writeJson(out, result);
  console.log(`status=${result.status}  (${result.evidence.reporter_output})`);
  return result;
}
