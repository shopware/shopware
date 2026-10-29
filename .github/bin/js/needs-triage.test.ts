import assert from 'node:assert/strict';
import { test } from 'node:test';
import { NEEDS_TRIAGE_LABEL, decide, manageNeedsTriage, shouldHandle } from './needs-triage.ts';

type Item = { number: number; state?: string; title?: string; draft?: boolean };

const contextFor = (
    eventName: 'issues' | 'pull_request_target',
    action: string,
    item: Item,
    label?: string,
) => ({
    eventName,
    repo: { owner: 'shopware', repo: 'shopware' },
    payload: {
        action,
        ...(label === undefined ? {} : { label: { name: label } }),
        ...(eventName === 'issues' ? { issue: item } : { pull_request: item }),
    },
});

test('decide: a domain label next to needs-triage removes needs-triage', () => {
    assert.equal(decide(['domain/framework', NEEDS_TRIAGE_LABEL]), 'remove');
    assert.equal(decide(['service/storefront', 'bug', NEEDS_TRIAGE_LABEL]), 'remove');
});

test('decide: no domain label and no needs-triage adds needs-triage', () => {
    assert.equal(decide([]), 'add');
    assert.equal(decide(['bug', 'external-contribution']), 'add');
});

test('decide: an item already in the right state is kept', () => {
    assert.equal(decide(['domain/framework']), 'keep');
    assert.equal(decide([NEEDS_TRIAGE_LABEL]), 'keep');
});

test('shouldHandle: a labeled event only counts for domain and service labels', () => {
    assert.equal(shouldHandle(contextFor('issues', 'labeled', { number: 1 }, 'domain/framework')), null);
    assert.equal(shouldHandle(contextFor('pull_request_target', 'labeled', { number: 1 }, 'service/sbp')), null);
    assert.match(shouldHandle(contextFor('issues', 'labeled', { number: 1 }, 'bug')) ?? '', /no domain label/);
});

test('shouldHandle: a domain label removes needs-triage from drafts and closed items too', () => {
    assert.equal(
        shouldHandle(contextFor('pull_request_target', 'labeled', { number: 1, draft: true }, 'domain/framework')),
        null,
    );
    assert.equal(
        shouldHandle(contextFor('issues', 'labeled', { number: 1, state: 'closed' }, 'domain/framework')),
        null,
    );
});

test('shouldHandle: opened drafts, closed items and test plans are not triaged', () => {
    assert.match(shouldHandle(contextFor('pull_request_target', 'opened', { number: 1, draft: true })) ?? '', /draft/);
    assert.match(shouldHandle(contextFor('issues', 'opened', { number: 1, state: 'closed' })) ?? '', /closed/);
    assert.match(
        shouldHandle(contextFor('issues', 'opened', { number: 1, title: '[Test Plan] 6.7.17.0' })) ?? '',
        /test plan/,
    );
    assert.equal(shouldHandle(contextFor('issues', 'opened', { number: 1, state: 'open', title: 'Bug' })), null);
    assert.equal(shouldHandle(contextFor('pull_request_target', 'ready_for_review', { number: 1, draft: false })), null);
});

function fakeGitHub(labels: string[]) {
    const calls: string[] = [];
    const github = {
        rest: {
            issues: {
                async listLabelsOnIssue() {
                    return { data: labels.map((name) => ({ name })) };
                },
                async addLabels(options: { issue_number: number; labels: string[] }) {
                    calls.push(`add ${options.labels.join(',')} to #${options.issue_number}`);
                },
                async removeLabel(options: { issue_number: number; name: string }) {
                    calls.push(`remove ${options.name} from #${options.issue_number}`);
                },
            },
        },
    };
    const core = { info() {} };

    return { github, core, calls };
}

test('manageNeedsTriage: assigning a domain label removes needs-triage', async () => {
    const { github, core, calls } = fakeGitHub(['domain/framework', NEEDS_TRIAGE_LABEL]);
    const context = contextFor('pull_request_target', 'labeled', { number: 22233 }, 'domain/framework');

    assert.equal(await manageNeedsTriage({ github, core, context }), 'remove');
    assert.deepEqual(calls, ['remove needs-triage from #22233']);
});

test('manageNeedsTriage: an opened issue without a domain label gets needs-triage', async () => {
    const { github, core, calls } = fakeGitHub(['bug']);
    const context = contextFor('issues', 'opened', { number: 7, state: 'open', title: 'Bug' });

    assert.equal(await manageNeedsTriage({ github, core, context }), 'add');
    assert.deepEqual(calls, ['add needs-triage to #7']);
});

test('manageNeedsTriage: a skipped event touches no label', async () => {
    const { github, core, calls } = fakeGitHub([]);
    const context = contextFor('issues', 'labeled', { number: 7 }, 'bug');

    assert.equal(await manageNeedsTriage({ github, core, context }), null);
    assert.deepEqual(calls, []);
});
