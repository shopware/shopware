// Dispatch the bundle to its executor and write the leg result. This is the single "run the test
// and classify" step; the surrounding setup (reset/seed/readiness) lives in pipeline.mjs.
import { FILES, readJson, writeJson, makeResult } from './lib.mjs';
import { run as runHttp } from './executors/http.mjs';
import { run as runPlaywright } from './executors/playwright.mjs';
import { run as runDirect } from './executors/direct.mjs';

const EXECUTORS = { http: runHttp, playwright: runPlaywright, direct: runDirect };

export async function runBundle({ target, out }) {
  const plan = readJson(FILES.plan);
  const executor = EXECUTORS[plan.executor];
  const result = executor ? await executor({ plan, target }) : makeResult({
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
