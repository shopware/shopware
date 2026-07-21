import { test } from 'node:test';
import assert from 'node:assert/strict';
import { ReproductionExecutor } from './base.ts';
import type { ExecutorContext, ExecutorInput, Fragment, LegResultLike } from './base.ts';
import type { Plan } from '../types.ts';

const PLAN: Partial<Plan> = { issue: 42, version: '6.6.0.0', executor: 'direct' };
const TARGET = 'reported';
const INPUT = (): ExecutorInput => ({ plan: PLAN, target: TARGET });

type Phase = 'prepare' | 'execute' | 'classify';
type Call = [Phase, ExecutorContext];

interface Overrides {
  prepare?: (input: ExecutorContext) => Fragment;
  execute?: (context: ExecutorContext) => Fragment;
  classify?: (context: ExecutorContext) => LegResultLike;
}

/**
 * Records the lifecycle so tests can assert both the order of the phases and the context each phase
 * received. Subclasses stay stateless in production; here we capture per-run into a shared array.
 */
class RecordingExecutor extends ReproductionExecutor {
  calls: Call[];
  overrides: Overrides;

  constructor(calls: Call[], overrides: Overrides = {}) {
    super('recording');
    this.calls = calls;
    this.overrides = overrides;
  }

  async prepare(input: ExecutorContext): Promise<Fragment> {
    this.calls.push(['prepare', input]);
    return this.overrides.prepare ? this.overrides.prepare(input) : { prepared: 'p' };
  }

  async execute(context: ExecutorContext): Promise<Fragment> {
    this.calls.push(['execute', context]);
    return this.overrides.execute ? this.overrides.execute(context) : { executed: 'e' };
  }

  classify(context: ExecutorContext): LegResultLike {
    this.calls.push(['classify', context]);
    return this.overrides.classify ? this.overrides.classify(context) : { status: 'not_reproduced' };
  }
}

test('run() invokes prepare -> execute -> classify in order', async () => {
  const calls: Call[] = [];
  await new RecordingExecutor(calls).run(INPUT());
  assert.deepEqual(calls.map(([phase]) => phase), ['prepare', 'execute', 'classify']);
});

test('run() returns whatever classify() produces', async () => {
  const calls: Call[] = [];
  const result = await new RecordingExecutor(calls, {
    classify: () => ({ status: 'reproduced', tag: 'final' }),
  }).run(INPUT());
  assert.deepEqual(result, { status: 'reproduced', tag: 'final' });
});

test('run() threads input into prepare()', async () => {
  const calls: Call[] = [];
  const input = INPUT();
  await new RecordingExecutor(calls).run(input);
  const [, prepareArg] = calls.find(([phase]) => phase === 'prepare')!;
  assert.deepEqual(prepareArg, input);
});

test('run() merges input + prepare() output into the execute() context', async () => {
  const calls: Call[] = [];
  await new RecordingExecutor(calls).run(INPUT());
  const [, executeArg] = calls.find(([phase]) => phase === 'execute')!;
  assert.deepEqual(executeArg, { plan: PLAN, target: TARGET, prepared: 'p' });
});

test('run() merges execute() output on top of the prepared context for classify()', async () => {
  const calls: Call[] = [];
  await new RecordingExecutor(calls).run(INPUT());
  const [, classifyArg] = calls.find(([phase]) => phase === 'classify')!;
  assert.deepEqual(classifyArg, { plan: PLAN, target: TARGET, prepared: 'p', executed: 'e' });
});

test('later phases override earlier context keys with the same name', async () => {
  const calls: Call[] = [];
  await new RecordingExecutor(calls, {
    prepare: () => ({ shared: 'from-prepare' }),
    execute: () => ({ shared: 'from-execute' }),
  }).run(INPUT());
  const [, classifyArg] = calls.find(([phase]) => phase === 'classify')!;
  assert.equal(classifyArg.shared, 'from-execute');
});

test('prepare() returning { blocked } short-circuits before execute()/classify()', async () => {
  const calls: Call[] = [];
  const blockedLeg: LegResultLike = { status: 'blocked' };
  const result = await new RecordingExecutor(calls, {
    prepare: () => ({ blocked: blockedLeg }),
  }).run(INPUT());
  assert.equal(result, blockedLeg);
  assert.deepEqual(calls.map(([phase]) => phase), ['prepare']);
});

test('execute() returning { blocked } short-circuits before classify()', async () => {
  const calls: Call[] = [];
  const blockedLeg: LegResultLike = { status: 'blocked' };
  const result = await new RecordingExecutor(calls, {
    execute: () => ({ blocked: blockedLeg }),
  }).run(INPUT());
  assert.equal(result, blockedLeg);
  assert.deepEqual(calls.map(([phase]) => phase), ['prepare', 'execute']);
});

test('a falsy blocked value does not short-circuit (only truthy blocked stops the flow)', async () => {
  const calls: Call[] = [];
  await new RecordingExecutor(calls, {
    prepare: () => ({ blocked: null, prepared: 'p' }),
  }).run(INPUT());
  assert.deepEqual(calls.map(([phase]) => phase), ['prepare', 'execute', 'classify']);
});

test('the blocked() helper builds a canonical blocked leg', async () => {
  const exec = new RecordingExecutor([]);
  const leg = exec.blocked(INPUT(), { reason: 'login failed' });
  assert.equal(leg.status, 'blocked');
  assert.equal(leg.blocked_reason, 'login failed');
  assert.equal(leg.evidence.reporter_output, 'login failed');
  assert.deepEqual(leg.assertion, { expect: null, actual: null, matched: null });
  assert.equal(leg.issue, 42);
  assert.equal(leg.target, TARGET);
  assert.equal(leg.executor, 'direct');
});

test('blocked() merges extra evidence while keeping reporter_output as the reason', () => {
  const exec = new RecordingExecutor([]);
  const leg = exec.blocked(INPUT(), {
    reason: 'missing spec file',
    evidence: { artifacts: [{ kind: 'screenshot', name: '/tmp/x.png' }] },
  });
  assert.equal(leg.evidence.reporter_output, 'missing spec file');
  assert.deepEqual(leg.evidence.artifacts, [{ kind: 'screenshot', name: '/tmp/x.png' }]);
});

test('a subclass converts a setup failure into a blocked leg via prepare()', async () => {
  // The canonical blocked-leg pattern: prepare() detects a setup problem and returns
  // { blocked: this.blocked(...) }, which run() surfaces directly without executing.
  class SetupFailingExecutor extends ReproductionExecutor {
    constructor() {
      super('setup-failing');
    }

    async prepare(context: ExecutorInput) {
      return { blocked: this.blocked(context, { reason: 'generated file absent' }) };
    }
  }

  const result = await new SetupFailingExecutor().run(INPUT());
  assert.equal(result.status, 'blocked');
  assert.equal(result.blocked_reason, 'generated file absent');
});

test('result() delegates to makeResult and yields the canonical envelope', () => {
  const exec = new RecordingExecutor([]);
  const leg = exec.result(INPUT(), {
    status: 'reproduced',
    assertion: { expect: 'a', actual: 'b', matched: true },
    evidence: { script: 'echo hi' },
  });
  assert.equal(leg.schema_version, '1');
  assert.equal(leg.status, 'reproduced');
  assert.equal(leg.evidence.script, 'echo hi');
  assert.equal(leg.evidence.script_lang, 'sh');
  assert.deepEqual(leg.evidence.http, []);
  assert.equal(leg.blocked_reason, null);
});

test('nullAssertion() returns the empty tri-state assertion', () => {
  const exec = new RecordingExecutor([]);
  assert.deepEqual(exec.nullAssertion(), { expect: null, actual: null, matched: null });
});

test('the constructor stores the executor name', () => {
  assert.equal(new ReproductionExecutor('playwright').name, 'playwright');
});

test('default prepare() returns an empty fragment', async () => {
  const base = new ReproductionExecutor('base');
  assert.deepEqual(await base.prepare(), {});
});

test('default execute() throws a name-tagged not-implemented error', async () => {
  const base = new ReproductionExecutor('base');
  await assert.rejects(() => base.execute(), /base executor does not implement execute\(\)/);
});

test('default classify() throws a name-tagged not-implemented error', () => {
  const base = new ReproductionExecutor('base');
  assert.throws(() => base.classify(), /base executor does not implement classify\(\)/);
});

test('a throw inside execute() propagates out of run() (caller turns it into a blocked leg)', async () => {
  // base.run() intentionally does NOT catch: the throw->blocked conversion lives in the caller
  // (cli/execute-bundle.ts), so run() must reject and let that wrapper record the blocked leg.
  class ThrowingExecutor extends ReproductionExecutor {
    constructor() {
      super('throwing');
    }

    async execute(): Promise<Fragment> {
      throw new Error('invalid regex in assertion');
    }
  }

  await assert.rejects(() => new ThrowingExecutor().run(INPUT()), /invalid regex in assertion/);
});
