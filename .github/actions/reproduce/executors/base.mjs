import { makeResult } from '../bundle.mjs';

/**
 * Shared contract for reproduction executors.
 *
 * Every executor follows the same stateless lifecycle: prepare the run, execute the runner or
 * request, then classify the output into the canonical `result.json` shape.
 */
export class ReproductionExecutor {
  constructor(name) {
    this.name = name;
  }

  /**
   * Runs the common executor lifecycle without storing per-run state on the executor instance.
   *
   * Subclasses return plain context fragments from `prepare()` and `execute()`. This keeps executor
   * objects reusable while still making each phase read like a named step in the reproduction flow.
   *
   * @returns A canonical result object ready to be written to `result.json`.
   */
  async run(input) {
    const prepared = await this.prepare(input);
    if (prepared?.blocked) {
      return prepared.blocked;
    }

    const preparedContext = { ...input, ...prepared };
    const execution = await this.execute(preparedContext);
    if (execution?.blocked) {
      return execution.blocked;
    }

    return this.classify({ ...preparedContext, ...execution });
  }

  async prepare() {
    return {};
  }

  async execute() {
    throw new Error(`${this.name} executor does not implement execute()`);
  }

  classify() {
    throw new Error(`${this.name} executor does not implement classify()`);
  }

  /**
   * Converts executor-specific evidence into the shared result contract.
   *
   * Keeping this here prevents individual executors from hand-assembling subtly different result
   * envelopes when only the assertion, evidence, and blocker text vary by executor.
   */
  result({ plan, target }, { status, assertion, evidence = {}, blockedReason = null }) {
    return makeResult({ plan, target, status, assertion, evidence, blockedReason });
  }

  nullAssertion() {
    return { expect: null, actual: null, matched: null };
  }

  /**
   * Produces a blocked result for setup or environment failures before classification can happen.
   *
   * Executors use this for missing generated files, failed harness login, or other conditions that
   * prevent the issue symptom from being judged at all.
   */
  blocked(context, { reason, assertion = this.nullAssertion(), evidence = {} }) {
    return this.result(context, {
      status: 'blocked',
      assertion,
      evidence: { reporter_output: reason, ...evidence },
      blockedReason: reason,
    });
  }
}
