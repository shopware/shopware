import assert from 'node:assert/strict';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';
import { type ItemOnProject, planPullRequestSync, resolvePullRequestNumber, syncPullRequest, toItemsOnProjects } from './waiting-on-event.ts';
import type { Row } from './waiting-on.ts';

const row = (overrides: Partial<Row> = {}): Row => ({
    id: 'PR_1',
    number: 1,
    title: 'a pull request',
    url: 'https://github.com/shopware/shopware/pull/1',
    author: 'someone',
    authorAssociation: 'CONTRIBUTOR',
    labels: [],
    waitingOn: 'shopware',
    reason: 'never-reviewed',
    label: 'waiting-on/shopware',
    since: '2026-09-01T10:00:00Z',
    days: 29,
    ...overrides,
});

const item = (overrides: Partial<ItemOnProject> = {}): ItemOnProject => ({
    id: 'ITEM_1',
    pullRequestId: 'PR_1',
    values: { waitingOn: 'shopware', reason: 'never-reviewed', since: '2026-09-01' },
    isArchived: false,
    owner: 'shopware',
    projectNumber: 69,
    ...overrides,
});

test('resolvePullRequestNumber takes the event number, or the relay file when there is one', () => {
    assert.equal(resolvePullRequestNumber('21447', undefined), 21447);

    const relayFile = join(mkdtempSync(join(tmpdir(), 'relay-')), 'pr-number.txt');
    assert.equal(resolvePullRequestNumber('5', relayFile), 5, 'a relay file that does not exist falls back to the event');
    writeFileSync(relayFile, '20698\n');
    assert.equal(resolvePullRequestNumber('', relayFile), 20698);
});

test('resolvePullRequestNumber rejects anything but a plain number', () => {
    assert.throws(() => resolvePullRequestNumber('', undefined), /Not a pull request number/);
    assert.throws(() => resolvePullRequestNumber('12; rm -rf /', undefined), /Not a pull request number/);
    assert.throws(() => resolvePullRequestNumber('0', undefined), /Not a pull request number/);
});

test('an open pull request missing from the project is added', () => {
    assert.deepEqual(planPullRequestSync(row(), undefined, true), [
        { kind: 'add', row: row(), set: { waitingOn: 'shopware', reason: 'never-reviewed', since: '2026-09-01' } },
    ]);
});

test('an open pull request on the project only gets the fields that changed', () => {
    const changed = row({ waitingOn: 'author', reason: 'changes-requested' });

    assert.deepEqual(planPullRequestSync(changed, item(), true), [
        { kind: 'update', itemId: 'ITEM_1', row: changed, set: { waitingOn: 'author', reason: 'changes-requested' } },
    ]);
});

test('an open pull request whose item was archived is added again, which unarchives it', () => {
    assert.equal(planPullRequestSync(row(), item({ isArchived: true }), true)[0].kind, 'add');
});

test('a closed pull request is archived on the main project and left alone on a team project', () => {
    assert.deepEqual(planPullRequestSync(undefined, item(), true), [{ kind: 'archive', itemId: 'ITEM_1' }]);
    assert.deepEqual(planPullRequestSync(undefined, item(), false), []);
    assert.deepEqual(planPullRequestSync(undefined, item({ isArchived: true }), true), []);
    assert.deepEqual(planPullRequestSync(undefined, undefined, true), []);
});

test('toItemsOnProjects reads the project and the field values of each item', () => {
    const items = toItemsOnProjects(
        [{
            id: 'ITEM_1',
            isArchived: false,
            project: { number: 43, owner: { login: 'shopware' } },
            fieldValues: { nodes: [{ name: 'author', field: { name: 'Waiting on' } }, { date: '2026-09-01', field: { name: 'Waiting since' } }] },
        }],
        'PR_1',
    );

    assert.deepEqual(items, [{ id: 'ITEM_1', pullRequestId: 'PR_1', values: { waitingOn: 'author', since: '2026-09-01' }, isArchived: false, owner: 'shopware', projectNumber: 43 }]);
});

const pullRequestNode = (overrides: Record<string, unknown> = {}) => ({
    state: 'OPEN',
    id: 'PR_1',
    number: 1,
    title: 'a pull request',
    url: 'https://github.com/shopware/shopware/pull/1',
    createdAt: '2026-08-01T00:00:00Z',
    isDraft: false,
    authorAssociation: 'CONTRIBUTOR',
    reviewDecision: null,
    mergeable: 'MERGEABLE',
    author: { login: 'someone', __typename: 'User' },
    labels: { nodes: [{ name: 'domain/crm-after-sales' }] },
    approvals: { totalCount: 0 },
    allReviews: { totalCount: 0 },
    latestOpinionatedReviews: { nodes: [] },
    reviewThreads: { nodes: [] },
    timelineItems: { nodes: [] },
    ...overrides,
});

const schema = {
    organization: {
        projectV2: {
            id: 'PROJECT',
            fields: {
                nodes: [
                    { id: 'F_WAITING', name: 'Waiting on', dataType: 'SINGLE_SELECT', options: [{ id: 'O_SHOPWARE', name: 'shopware' }] },
                    { id: 'F_REASON', name: 'Reason', dataType: 'SINGLE_SELECT', options: [{ id: 'O_NEVER', name: 'never-reviewed' }] },
                    { id: 'F_SINCE', name: 'Waiting since', dataType: 'DATE' },
                ],
            },
        },
    },
};

function fakes(pullRequest: Record<string, unknown>, projectItems: unknown[]) {
    const labels: string[] = [];
    const writes: string[] = [];
    const core = { info: () => {}, warning: () => {}, error: () => {}, setOutput: () => {}, summary: { addRaw: () => {}, write: async () => {} } };
    const github = {
        async graphql<T>(): Promise<T> {
            return { repository: { pullRequest } } as T;
        },
        rest: {
            issues: {
                async addLabels({ labels: added }: { labels: string[] }) {
                    labels.push(...added);
                },
                async removeLabel() {},
            },
        },
    };
    const projectGithub = {
        async graphql<T>(query: string, variables: Record<string, unknown>): Promise<T> {
            if (query.includes('projectItems')) {
                return { node: { projectItems: { nodes: projectItems } } } as T;
            }
            if (query.includes('fields(first: 50)')) {
                return schema as T;
            }
            if (query.includes('addProjectV2ItemById')) {
                writes.push(`add ${variables.projectId}`);
                return { addProjectV2ItemById: { item: { id: 'NEW_ITEM' } } } as T;
            }
            writes.push(query.includes('archiveProjectV2Item') ? `archive ${variables.itemId}` : `set ${variables.itemId}`);
            return {} as T;
        },
    };

    return { labels, writes, core, github, projectGithub };
}

const context = { repo: { owner: 'shopware', repo: 'shopware' } };

test('syncPullRequest labels an open pull request and adds it to the main and its team project', async () => {
    const { labels, writes, core, github, projectGithub } = fakes(pullRequestNode(), []);

    await syncPullRequest({ github, core, context }, { number: 1, project: { github: projectGithub, number: 69, teams: '{"domain/crm-after-sales":43,"domain/other":7}' } });

    assert.deepEqual(labels, ['waiting-on/shopware']);
    assert.deepEqual(writes, ['add PROJECT', 'set NEW_ITEM', 'add PROJECT', 'set NEW_ITEM']);
});

test('syncPullRequest archives a closed pull request on the main project only and leaves its label', async () => {
    const onBoth = [
        { id: 'ITEM_MAIN', isArchived: false, project: { number: 69, owner: { login: 'shopware' } }, fieldValues: { nodes: [] } },
        { id: 'ITEM_TEAM', isArchived: false, project: { number: 43, owner: { login: 'shopware' } }, fieldValues: { nodes: [] } },
    ];
    const { labels, writes, core, github, projectGithub } = fakes(pullRequestNode({ state: 'MERGED' }), onBoth);

    await syncPullRequest({ github, core, context }, { number: 1, project: { github: projectGithub, number: 69, teams: '{"domain/crm-after-sales":43}' } });

    assert.deepEqual(labels, []);
    assert.deepEqual(writes, ['archive ITEM_MAIN']);
});

test('syncPullRequest writes nothing in a dry run', async () => {
    const { labels, writes, core, github, projectGithub } = fakes(pullRequestNode(), []);

    await syncPullRequest({ github, core, context }, { number: 1, dryRun: true, project: { github: projectGithub, number: 69 } });

    assert.deepEqual(labels, []);
    assert.deepEqual(writes, []);
});
