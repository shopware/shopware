import fs from 'node:fs';
import { resolveBundlePlaceholders } from '../../admin-api.mjs';
import { ReproductionExecutor } from '../base.mjs';
import { HttpRequestPreparer } from './request-preparer.mjs';
import { HttpRequestSequenceRunner } from './request-sequence-runner.mjs';
import { classifyHttpAssertions } from './assertion-classifier.mjs';

/**
 * Executes API reproductions as deterministic HTTP request sequences.
 *
 * The executor composes request preparation, sequence execution, and assertion classification so
 * auth/session handling cannot leak into the verdict rules.
 */
export class HttpExecutor extends ReproductionExecutor {
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
  async prepare({ plan }) {
    const requests = plan.requests || [plan.request];
    const assertions = plan.assertions || (plan.assertion ? [plan.assertion] : []);
    const ids = await resolveBundlePlaceholders({
      values: [requests, assertions],
      includeSalesChannelAccessKey: requests.some((request) => this.requestPreparer.isStorePath(request?.path || '')),
    });

    return { requests, assertions, ids };
  }

  /**
   * Runs the request sequence and writes the reproduction shell preview.
   *
   * The script is evidence for humans only; auth and session headers are injected by the executor at
   * runtime, so the field is named `fakeScript` until it is embedded into the result evidence.
   */
  async execute({ requests, ids }) {
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
  classify(context) {
    const { assertions, evaluation } = context;
    const { status, checks, reporter } = classifyHttpAssertions(assertions, evaluation);

    return this.result(context, {
      status,
      assertion: { matched: status === 'not_reproduced', checks },
      evidence: {
        script: evaluation.fakeScript,
        script_lang: 'sh',
        reporter_output: reporter,
        http: [{ status: evaluation.code || 0 }],
      },
      blockedReason: ['blocked', 'inconclusive'].includes(status) ? reporter : null,
    });
  }
}

export const executor = new HttpExecutor();
