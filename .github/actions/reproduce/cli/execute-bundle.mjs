/**
 * Dispatches an authored bundle to its executor and writes the leg result.
 *
 * This is the single "run the test and classify" step; reset, seeding, and readiness checks live in
 * `full-run.mjs` so the trusted and preview flows share ordering.
 */
import { FILES, readJson, writeJson, makeResult } from '../bundle.mjs';

// Executors are imported lazily so a leg only loads the module chain it actually uses. Importing the
// Playwright executor eagerly pulls in `@playwright/test` (via boilerplate/login-state.mjs), which
// crashes an http/direct leg on a runner where Playwright was — correctly — not installed.
const EXECUTORS = {
  http: () => import('../executors/http/index.mjs'),
  playwright: () => import('../executors/playwright/index.mjs'),
  direct: () => import('../executors/direct/index.mjs'),
};

/**
 * Dispatches the authored bundle to its selected executor and writes the leg result.
 *
 * This is the single step that turns a prepared plan into `result.json`; reset, seeding, and
 * readiness checks stay in `full-run.mjs` so both trusted and preview runs share ordering.
 */
export async function executeBundle({ target, out }) {
  const plan = readJson(FILES.plan);
  const load = EXECUTORS[plan.executor];
  const result = load ? await (await load()).executor.run({ plan, target }) : makeResult({
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
