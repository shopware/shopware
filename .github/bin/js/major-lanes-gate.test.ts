import assert from 'node:assert/strict';
import { test } from 'node:test';
import { type CheckRun, checkMajorLanes, evaluateLane, requiredLanes } from './major-lanes-gate.ts';

const INTEGRATION = 'integration-major-check';
const ACCEPTANCE = 'acceptance-major / acceptance-major-check';
const DOWNSTREAM = 'downstream-major-check';

const run = (name: string, conclusion: string | null, startedAt: string, status = 'completed'): CheckRun =>
    ({ name, status, conclusion, started_at: startedAt });

test('requiredLanes maps each major-test label to its lane', () => {
    assert.deepEqual(requiredLanes([]), []);
    assert.deepEqual(requiredLanes(['major/6.8', 'domain/core']), []);
    assert.deepEqual(requiredLanes(['major-php']), [INTEGRATION]);
    assert.deepEqual(requiredLanes(['major-js']), [INTEGRATION]);
    assert.deepEqual(requiredLanes(['major-acceptance']), [ACCEPTANCE]);
    assert.deepEqual(requiredLanes(['major-tests']), [INTEGRATION, ACCEPTANCE, DOWNSTREAM]);
});

test('evaluateLane fails a lane that never ran instead of passing it', () => {
    assert.equal(evaluateLane(INTEGRATION, []).state, 'failure');
});

test('evaluateLane ignores skipped runs from unrelated events', () => {
    const runs = [
        run(INTEGRATION, 'success', '2026-10-15T06:00:00Z'),
        run(INTEGRATION, 'skipped', '2026-10-15T07:00:00Z'),
    ];
    assert.equal(evaluateLane(INTEGRATION, runs).state, 'success');

    assert.equal(evaluateLane(INTEGRATION, [run(INTEGRATION, 'skipped', '2026-10-15T07:00:00Z')]).state, 'failure');
});

test('evaluateLane judges the most recent run, so a fixed re-run turns it green', () => {
    const fixed = [
        run(INTEGRATION, 'failure', '2026-10-15T06:00:00Z'),
        run(INTEGRATION, 'success', '2026-10-15T07:00:00Z'),
    ];
    assert.equal(evaluateLane(INTEGRATION, fixed).state, 'success');

    const broken = [
        run(INTEGRATION, 'success', '2026-10-15T06:00:00Z'),
        run(INTEGRATION, 'failure', '2026-10-15T07:00:00Z'),
    ];
    assert.equal(evaluateLane(INTEGRATION, broken).state, 'failure');
});

test('evaluateLane preserves a downstream failure after an unrelated label skips the lane', () => {
    assert.equal(evaluateLane(DOWNSTREAM, [
        run(DOWNSTREAM, 'failure', '2026-10-15T06:00:00Z'),
        run(DOWNSTREAM, 'skipped', '2026-10-15T07:00:00Z'),
    ]).state, 'failure');
});

test('evaluateLane reports a running lane as pending', () => {
    assert.equal(evaluateLane(INTEGRATION, [run(INTEGRATION, null, '2026-10-15T07:00:00Z', 'in_progress')]).state, 'pending');
});

test('evaluateLane only looks at the lane it judges', () => {
    assert.equal(evaluateLane(INTEGRATION, [run(ACCEPTANCE, 'success', '2026-10-15T07:00:00Z')]).state, 'failure');
});

type Fixture = { labels: Record<number, string[]>; runs: Record<string, CheckRun[][]> };

function toolkit({ labels, runs }: Fixture) {
    const failures: string[] = [];
    const calls: Record<string, number> = {};
    const github = {
        rest: {
            pulls: {
                get: async ({ pull_number }: { pull_number: number }) =>
                    ({ data: { number: pull_number, head: { sha: `sha-${pull_number}` }, labels: labels[pull_number].map((name) => ({ name })) } }),
            },
            checks: {
                // Each call returns the next snapshot, the last one repeating, to simulate a lane finishing.
                listForRef: async ({ ref, check_name }: { ref: string; check_name: string }) => {
                    const key = `${ref} ${check_name}`;
                    const snapshots = runs[key] ?? [[]];
                    const index = Math.min(calls[key] ?? 0, snapshots.length - 1);
                    calls[key] = (calls[key] ?? 0) + 1;

                    return { data: { check_runs: snapshots[index] } };
                },
            },
            repos: {
                compareCommitsWithBasehead: async () => ({ data: { commits: [] } }),
                listPullRequestsAssociatedWithCommit: async () => ({ data: [] }),
            },
        },
    };
    const core = {
        info() {},
        warning() {},
        error() {},
        setFailed(message: string) {
            failures.push(message);
        },
        summary: { addRaw: () => ({ write: async () => undefined }) },
    };
    const context = {
        repo: { owner: 'shopware', repo: 'shopware' },
        payload: { merge_group: { head_ref: 'refs/heads/gh-readonly-queue/trunk/pr-20934-abc123', base_sha: 'base', head_sha: 'head' } },
    };

    return { toolkit: { github, core, context }, failures };
}

const instant = { sleep: async () => undefined };

test('checkMajorLanes passes a queued PR without major-test labels', async () => {
    const { toolkit: kit, failures } = toolkit({ labels: { 20934: ['major/6.8'] }, runs: {} });
    await checkMajorLanes(kit, instant);
    assert.deepEqual(failures, []);
});

test('checkMajorLanes blocks a labeled PR whose lane is red', async () => {
    const { toolkit: kit, failures } = toolkit({
        labels: { 20934: ['major-php'] },
        runs: { [`sha-20934 ${INTEGRATION}`]: [[run(INTEGRATION, 'failure', '2026-10-15T07:00:00Z')]] },
    });
    await checkMajorLanes(kit, instant);
    assert.equal(failures.length, 1);
    assert.match(failures[0], /#20934 integration-major-check concluded failure/);
});

test('checkMajorLanes waits for a running lane and passes once it is green', async () => {
    const { toolkit: kit, failures } = toolkit({
        labels: { 20934: ['major-php'] },
        runs: {
            [`sha-20934 ${INTEGRATION}`]: [
                [run(INTEGRATION, null, '2026-10-15T07:00:00Z', 'in_progress')],
                [run(INTEGRATION, 'success', '2026-10-15T07:00:00Z')],
            ],
        },
    });
    await checkMajorLanes(kit, instant);
    assert.deepEqual(failures, []);
});

test('checkMajorLanes blocks a major-tests PR when only Commercial failed', async () => {
    const { toolkit: kit, failures } = toolkit({
        labels: { 20934: ['major-tests'] },
        runs: {
            [`sha-20934 ${INTEGRATION}`]: [[run(INTEGRATION, 'success', '2026-10-15T06:00:00Z')]],
            [`sha-20934 ${ACCEPTANCE}`]: [[run(ACCEPTANCE, 'success', '2026-10-15T06:00:00Z')]],
            [`sha-20934 ${DOWNSTREAM}`]: [[
                run(DOWNSTREAM, 'failure', '2026-10-15T06:00:00Z'),
                run(DOWNSTREAM, 'skipped', '2026-10-15T07:00:00Z'),
            ]],
        },
    });
    await checkMajorLanes(kit, instant);
    assert.equal(failures.length, 1);
    assert.match(failures[0], /downstream-major-check concluded failure/);
});

test('checkMajorLanes fails a lane still running at the deadline', async () => {
    let clock = 0;
    const { toolkit: kit, failures } = toolkit({
        labels: { 20934: ['major-acceptance'] },
        runs: { [`sha-20934 ${ACCEPTANCE}`]: [[run(ACCEPTANCE, null, '2026-10-15T07:00:00Z', 'in_progress')]] },
    });
    await checkMajorLanes(kit, { timeoutMs: 3, pollIntervalMs: 1, now: () => clock, sleep: async (ms) => { clock += ms; } });
    assert.equal(failures.length, 1);
    assert.match(failures[0], /still in_progress/);
});
