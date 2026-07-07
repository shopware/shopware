import { ReproductionExecutor } from '../base.mjs';
import { preparePlaywrightSpec } from './spec-preparer.mjs';
import { PlaywrightAuthPreparer } from './auth-preparer.mjs';
import { PlaywrightRunner } from './runner.mjs';
import { classifyPlaywrightReport } from './report-classifier.mjs';

/**
 * Executes UI reproductions through Playwright and classifies the report as a symptom verdict.
 *
 * Auth, spec sanitizing, process execution, and report interpretation are separate collaborators
 * because each has different failure semantics in the reproduction pipeline.
 */
export class PlaywrightExecutor extends ReproductionExecutor {
  constructor() {
    super('playwright');
    this.authPreparer = new PlaywrightAuthPreparer();
    this.runner = new PlaywrightRunner();
  }

  /**
   * Prepares the runnable spec and harness browser state before Playwright is started.
   */
  prepare(context) {
    const spec = preparePlaywrightSpec(context);
    if (spec.blockedReason) {
      return {
        blocked: this.blocked(context, {
          reason: spec.blockedReason,
          evidence: spec.evidence,
        }),
      };
    }

    const auth = this.authPreparer.prepare(context.plan, context.target);
    if (auth.blockedReason) {
      return {
        blocked: this.blocked(context, {
          reason: auth.blockedReason,
          evidence: auth.evidence,
        }),
      };
    }

    return { ...spec, ...auth };
  }

  execute(context) {
    return this.runner.run(context);
  }

  /**
   * Converts Playwright's JSON report into the shared result envelope.
   */
  classify(context) {
    const outcome = classifyPlaywrightReport(context);

    return this.result(context, {
      status: outcome.status,
      assertion: {
        expect: 'spec passes (healthy)',
        actual: outcome.actual,
        matched: outcome.status === 'not_reproduced' ? true : outcome.status === 'reproduced' ? false : null,
      },
      evidence: {
        script: context.spec,
        script_lang: 'ts',
        reporter_output: outcome.reporter,
        artifacts: [{ kind: 'playwright-results', name: 'test-results/', run_artifact: `repro-${context.target}` }],
      },
      blockedReason: outcome.reason,
    });
  }
}

export const executor = new PlaywrightExecutor();
