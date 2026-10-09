import { ReproductionExecutor } from '../base.ts';
import type { ExecutorInput, Fragment } from '../base.ts';
import { preparePlaywrightSpec } from './spec-preparer.ts';
import { preparePlaywrightAuth } from './auth-preparer.ts';
import { runPlaywright } from './runner.ts';
import type { RunnerContext } from './runner.ts';
import { classifyPlaywrightReport } from './report-classifier.ts';
import type { PlaywrightReport } from './report-classifier.ts';
import type { LegResult, Plan } from '../../types.ts';

/** The run context threaded into classify(): the prepared runner context plus the executed report. */
type ClassifyContext = Omit<RunnerContext, 'plan'> & {
  plan: Partial<Plan> & { version: string };
  report: PlaywrightReport | null;
};

/**
 * Executes UI reproductions through Playwright and classifies the report as a symptom verdict.
 *
 * Auth, spec sanitizing, process execution, and report interpretation are separate collaborators
 * because each has different failure semantics in the reproduction pipeline.
 */
export class PlaywrightExecutor extends ReproductionExecutor {
  constructor() {
    super('playwright');
  }

  /**
   * Prepares the runnable spec and harness browser state before Playwright is started.
   */
  async prepare(context: ExecutorInput): Promise<Fragment> {
    const spec = preparePlaywrightSpec(context);
    if (spec.blockedReason) {
      return {
        blocked: this.blocked(context, {
          reason: spec.blockedReason,
          evidence: spec.evidence,
        }),
      };
    }

    const auth = preparePlaywrightAuth(context.plan, context.target);
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

  async execute(context: RunnerContext): Promise<Fragment> {
    return runPlaywright(context);
  }

  /**
   * Converts Playwright's JSON report into the shared result envelope.
   */
  classify(context: ClassifyContext): LegResult {
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
