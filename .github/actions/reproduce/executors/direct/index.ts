import fs from 'node:fs';
import { FILES } from '../../bundle.ts';
import { ReproductionExecutor } from '../base.ts';
import type { ExecutorInput, Fragment } from '../base.ts';
import type { LegResult } from '../../types.ts';
import { prepareDirectSpec } from './spec-preparer.ts';
import { runPhpunit } from './phpunit-runner.ts';
import { classifyPhpunitOutput } from './output-classifier.ts';

/** Context after {@link prepareDirectSpec} has resolved the authored test and shop location. */
type PreparedContext = ExecutorInput & { specPath: string; shop: string; spec: string };

/** Context handed to {@link DirectExecutor.classify} once PHPUnit output has been captured. */
type ClassifyContext = PreparedContext & { output: string };

/**
 * Executes service-layer reproductions through Shopware's PHPUnit integration suite.
 *
 * The direct executor is intentionally split into preparation, execution, and output classification
 * so PHPUnit wiring stays separate from the verdict policy that interprets PHPUnit's text output.
 */
export class DirectExecutor extends ReproductionExecutor {
  constructor() {
    super('direct');
  }

  async prepare(input: ExecutorInput): Promise<Fragment> {
    return prepareDirectSpec(input);
  }

  /**
   * Copies the authored generated test into the Shopware test tree and captures PHPUnit output.
   *
   * A missing generated test blocks the leg because no issue-specific symptom was exercised.
   */
  async execute(context: PreparedContext): Promise<Fragment> {
    const { specPath, shop, plan, target } = context;
    const output = runPhpunit(specPath, shop, plan, target);
    if (output === null) {
      const reason = `generated test '${specPath}' not found`;

      return {
        blocked: this.blocked(context, {
          reason,
          evidence: { script: context.spec, script_lang: 'php' },
        }),
      };
    }

    fs.writeFileSync('phpunit-output.txt', output);

    return { output };
  }

  /**
   * Turns PHPUnit's process output into the reproduction status used by the report renderer.
   */
  classify(context: ClassifyContext): LegResult {
    const { plan, output } = context;
    const { status, matched, reporter, reason } = classifyPhpunitOutput(output, plan);

    return this.result(context, {
      status,
      assertion: { expect: 'test passes (healthy)', actual: reporter, matched },
      evidence: {
        script: context.spec,
        script_lang: 'php',
        reporter_output: reporter,
        artifacts: [{ kind: 'phpunit-test', name: FILES.testPhp }],
      },
      blockedReason: reason,
    });
  }
}

export const executor = new DirectExecutor();
