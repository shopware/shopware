import assert from 'node:assert/strict';
import { test } from 'node:test';
import { NEEDS_TRIAGE_LABEL, type NeedsTriageInput, evaluateNeedsTriage, manageNeedsTriage } from './needs-triage.ts';

const openItem = (overrides: Partial<NeedsTriageInput> = {}): NeedsTriageInput => ({
    labels: [],
    isDraft: false,
    isClosed: false,
    title: 'Bug',
    ...overrides,
});

test('a domain or service label next to needs-triage removes needs-triage', () => {
    assert.deepEqual(evaluateNeedsTriage(openItem({ labels: ['domain/framework', NEEDS_TRIAGE_LABEL] })), { action: 'remove' });
    assert.deepEqual(evaluateNeedsTriage(openItem({ labels: ['service/storefront', 'bug', NEEDS_TRIAGE_LABEL] })), { action: 'remove' });
});

test('assigning a domain label removes needs-triage from drafts and closed items too', () => {
    const labels = ['domain/framework', NEEDS_TRIAGE_LABEL];

    assert.deepEqual(evaluateNeedsTriage(openItem({ addedLabel: 'domain/framework', labels, isDraft: true })), { action: 'remove' });
    assert.deepEqual(evaluateNeedsTriage(openItem({ addedLabel: 'domain/framework', labels, isClosed: true })), { action: 'remove' });
});

test('an open item without a domain label gets needs-triage', () => {
    assert.deepEqual(evaluateNeedsTriage(openItem()), { action: 'add' });
    assert.deepEqual(evaluateNeedsTriage(openItem({ labels: ['bug', 'external-contribution'] })), { action: 'add' });
});

test('an item already in the right state is kept', () => {
    assert.deepEqual(evaluateNeedsTriage(openItem({ labels: ['domain/framework'] })), { action: 'keep' });
    assert.deepEqual(evaluateNeedsTriage(openItem({ labels: [NEEDS_TRIAGE_LABEL] })), { action: 'keep' });
});

test('a label that is no domain label changes nothing', () => {
    assert.deepEqual(evaluateNeedsTriage(openItem({ addedLabel: 'bug', labels: ['bug'] })), {
        action: 'skip',
        reason: 'label bug is no domain label',
    });
});

test('drafts, closed items and test plans are not triaged', () => {
    assert.equal(evaluateNeedsTriage(openItem({ isDraft: true })).action, 'skip');
    assert.equal(evaluateNeedsTriage(openItem({ isClosed: true })).action, 'skip');
    assert.equal(evaluateNeedsTriage(openItem({ title: '[Test Plan] 6.7.17.0' })).action, 'skip');
});

type Item = { number: number; state: string; title: string; draft?: boolean };

function toolkitFor(
    action: string,
    labels: string[],
    payload: { issue?: Item; pull_request?: Item; label?: { name: string } },
    removeError?: unknown,
) {
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
                    if (removeError !== undefined) {
                        throw removeError;
                    }
                },
            },
        },
    };
    const context = { repo: { owner: 'shopware', repo: 'shopware' }, payload: { action, ...payload } };

    return { toolkit: { github, core: { info() {} }, context }, calls };
}

test('assigning a domain label to a pull request removes needs-triage', async () => {
    const { toolkit, calls } = toolkitFor('labeled', ['domain/framework', NEEDS_TRIAGE_LABEL], {
        pull_request: { number: 22233, state: 'open', title: 'feat: something', draft: false },
        label: { name: 'domain/framework' },
    });

    assert.deepEqual(await manageNeedsTriage(toolkit), { action: 'remove' });
    assert.deepEqual(calls, ['remove needs-triage from #22233']);
});

test('an opened issue without a domain label gets needs-triage', async () => {
    const { toolkit, calls } = toolkitFor('opened', ['bug'], { issue: { number: 7, state: 'open', title: 'Bug' } });

    assert.deepEqual(await manageNeedsTriage(toolkit), { action: 'add' });
    assert.deepEqual(calls, ['add needs-triage to #7']);
});

test('a skipped event touches no label', async () => {
    const { toolkit, calls } = toolkitFor('labeled', ['bug'], {
        issue: { number: 7, state: 'open', title: 'Bug' },
        label: { name: 'bug' },
    });

    assert.equal((await manageNeedsTriage(toolkit)).action, 'skip');
    assert.deepEqual(calls, []);
});

test('a label already removed by a concurrent run counts as removed', async () => {
    const { toolkit } = toolkitFor(
        'labeled',
        ['domain/framework', 'service/sbp', NEEDS_TRIAGE_LABEL],
        { issue: { number: 7, state: 'open', title: 'Bug' }, label: { name: 'service/sbp' } },
        Object.assign(new Error('Label does not exist'), { status: 404 }),
    );

    assert.deepEqual(await manageNeedsTriage(toolkit), { action: 'remove' });
});

test('any other failure to remove the label fails the run', async () => {
    const { toolkit } = toolkitFor(
        'labeled',
        ['domain/framework', NEEDS_TRIAGE_LABEL],
        { issue: { number: 7, state: 'open', title: 'Bug' }, label: { name: 'domain/framework' } },
        Object.assign(new Error('Server Error'), { status: 500 }),
    );

    await assert.rejects(manageNeedsTriage(toolkit), /Server Error/);
});
