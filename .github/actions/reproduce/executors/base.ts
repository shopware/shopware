import { makeResult } from '../bundle.ts';
import type { Evidence, LegResult, Plan } from '../types.ts';

/**
 * The minimal input every executor receives: the agent-authored plan plus the target being judged.
 * Kept permissive on `plan` because the plan is agent-authored and validated downstream.
 */
export type ExecutorInput = {
  plan: Partial<Plan>;
  target: string;
};

/**
 * The accumulating context threaded through the executor lifecycle. Each phase merges its fragment
 * on top of the previous context, so the shape is intentionally open beyond the guaranteed input.
 */
export type ExecutorContext = Record<string, unknown>;

/**
 * A leg-result-shaped object. Executors produce canonical {@link LegResult}s via `result()`, but
 * `classify()`/`run()` are typed loosely so subclasses (and test doubles) can return their own
 * result envelopes without weakening the precise contract of the result builders.
 */
export type LegResultLike = LegResult | Record<string, unknown>;

/**
 * A context fragment returned by `prepare()`/`execute()`. A truthy `blocked` short-circuits the
 * lifecycle and is surfaced directly as the leg result.
 */
export interface Fragment {
  blocked?: LegResultLike | null;
  [key: string]: unknown;
}

/** Options accepted by {@link ReproductionExecutor.result}. */
export interface ResultOptions {
  status: LegResult['status'];
  assertion: LegResult['assertion'];
  evidence?: Partial<Evidence>;
  blockedReason?: string | null;
}

/** Options accepted by {@link ReproductionExecutor.blocked}. */
export interface BlockedOptions {
  reason: string;
  assertion?: LegResult['assertion'];
  evidence?: Partial<Evidence>;
}

/**
 * Shared contract for reproduction executors.
 *
 * Every executor follows the same stateless lifecycle: prepare the run, execute the runner or
 * request, then classify the output into the canonical `result.json` shape.
 */
export class ReproductionExecutor {
  name: string;

  constructor(name: string) {
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
  async run(input: ExecutorInput): Promise<LegResultLike> {
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

  async prepare(_input?: ExecutorContext): Promise<Fragment> {
    return {};
  }

  async execute(_context?: ExecutorContext): Promise<Fragment> {
    throw new Error(`${this.name} executor does not implement execute()`);
  }

  classify(_context?: ExecutorContext): LegResultLike {
    throw new Error(`${this.name} executor does not implement classify()`);
  }

  /**
   * Converts executor-specific evidence into the shared result contract.
   *
   * Keeping this here prevents individual executors from hand-assembling subtly different result
   * envelopes when only the assertion, evidence, and blocker text vary by executor.
   */
  result({ plan, target }: ExecutorInput, { status, assertion, evidence = {}, blockedReason = null }: ResultOptions): LegResult {
    return makeResult({ plan, target, status, assertion, evidence, blockedReason });
  }

  nullAssertion(): LegResult['assertion'] {
    return { expect: null, actual: null, matched: null };
  }

  /**
   * Produces a blocked result for setup or environment failures before classification can happen.
   *
   * Executors use this for missing generated files, failed harness login, or other conditions that
   * prevent the issue symptom from being judged at all.
   */
  blocked(context: ExecutorInput, { reason, assertion = this.nullAssertion(), evidence = {} }: BlockedOptions): LegResult {
    return this.result(context, {
      status: 'blocked',
      assertion,
      evidence: { reporter_output: reason, ...evidence },
      blockedReason: reason,
    });
  }
}
