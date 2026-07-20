import fs from 'node:fs';
import { resolveBundlePlaceholders } from '../../admin-api.ts';
import { ReproductionExecutor } from '../base.ts';
import { HttpRequestPreparer } from './request-preparer.ts';
import { HttpRequestSequenceRunner } from './request-sequence-runner.ts';
import type { HttpEvaluation } from './request-sequence-runner.ts';
import { classifyHttpAssertions } from './assertion-classifier.ts';
import type { ExecutorInput, Fragment, LegResultLike } from '../base.ts';
import type { HttpAssertion, HttpRequest } from '../../types.ts';

/** The accumulated context the HTTP executor classifies once the sequence has run. */
type HttpClassifyContext = ExecutorInput & {
  assertions: HttpAssertion[];
  evaluation: HttpEvaluation;
};

/**
 * Executes API reproductions as deterministic HTTP request sequences.
 *
 * The executor composes request preparation, sequence execution, and assertion classification so
 * auth/session handling cannot leak into the verdict rules.
 */
export class HttpExecutor extends ReproductionExecutor {
  requestPreparer: HttpRequestPreparer;

  requestRunner: HttpRequestSequenceRunner;

  constructor() {
    super('http');
    this.requestPreparer = new HttpRequestPreparer();
    this.requestRunner = new HttpRequestSequenceRunner(this.requestPreparer);
  }

  /**
   * Resolves plan placeholders once before the sequence runs.
   *
   * Store API access keys are requested only when the sequence actually targets Store API routes.
   */
  async prepare({ plan }: ExecutorInput): Promise<Fragment> {
    // An explicit `requests: []` is truthy, so guard on length before falling back to the single
    // `request`; an empty list yields no response and is classified inconclusive, never reproduced.
    const requests = plan.requests?.length ? plan.requests : (plan.request ? [plan.request] : []);
    const assertions = plan.assertions || (plan.assertion ? [plan.assertion] : []);
    const ids = await resolveBundlePlaceholders({
      values: [requests, assertions],
      includeSalesChannelAccessKey: requests.some((request: HttpRequest) => this.requestPreparer.isStorePath(request?.path || '')),
    });

    return { requests, assertions, ids };
  }

  /**
   * Runs the request sequence and writes the reproduction shell preview.
   *
   * The script is evidence for humans only; auth and session headers are injected by the executor at
   * runtime, so the field is named `fakeScript` until it is embedded into the result evidence.
   */
  async execute({ requests, ids }: { requests: HttpRequest[]; ids: Record<string, string> }): Promise<Fragment> {
    const evaluation = await this.requestRunner.send(requests, ids);
    fs.writeFileSync(
      'repro.sh',
      `#!/usr/bin/env bash\n# Reproduction request(s) -- set $APP_URL (executor injects auth + sw-context-token).\n${evaluation.fakeScript}`,
    );

    return { evaluation };
  }

  /**
   * Applies the plan assertions to the final HTTP response and emits canonical result evidence.
   */
  classify(context: HttpClassifyContext): LegResultLike {
    const { assertions, evaluation } = context;
    const { status, checks, reporter } = classifyHttpAssertions(assertions, evaluation);

    return this.result(context, {
      status,
      assertion: { matched: status === 'not_reproduced', checks },
      evidence: {
        script: evaluation.fakeScript,
        script_lang: 'sh',
        reporter_output: reporter,
        http: [{ status: (evaluation.code || 0) as number }],
      },
      blockedReason: ['blocked', 'inconclusive'].includes(status) ? reporter : null,
    });
  }
}

export const executor = new HttpExecutor();
