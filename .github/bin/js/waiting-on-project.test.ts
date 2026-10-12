import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    applyProjectChanges,
    buildFieldMutation,
    planProjectChanges,
    type ProjectItem,
    type ProjectSchema,
    resolveSchema,
    toProjectItems,
} from './waiting-on-project.ts';
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

const item = (overrides: Partial<ProjectItem> = {}): ProjectItem => ({
    id: 'ITEM_1',
    pullRequestId: 'PR_1',
    values: { waitingOn: 'shopware', reason: 'never-reviewed', since: '2026-09-01' },
    ...overrides,
});

const schema: ProjectSchema = resolveSchema('PROJECT', [
    { id: 'F_WAITING', name: 'Waiting on', dataType: 'SINGLE_SELECT', options: [{ id: 'O_SHOPWARE', name: 'shopware' }, { id: 'O_AUTHOR', name: 'author' }] },
    { id: 'F_REASON', name: 'Reason', dataType: 'SINGLE_SELECT', options: [{ id: 'O_CHANGES', name: 'changes-requested' }] },
    { id: 'F_SINCE', name: 'Waiting since', dataType: 'DATE' },
]);

test('an item whose values already match is left alone', () => {
    assert.deepEqual(planProjectChanges([row()], [item()]), []);
});

test('a pull request missing from the project is added with every field', () => {
    assert.deepEqual(planProjectChanges([row()], []), [
        { kind: 'add', row: row(), set: { waitingOn: 'shopware', reason: 'never-reviewed', since: '2026-09-01' } },
    ]);
});

test('only the fields that changed are written', () => {
    const changed = row({ waitingOn: 'author', reason: 'changes-requested', since: '2026-09-01T18:00:00Z' });

    assert.deepEqual(planProjectChanges([changed], [item()]), [
        { kind: 'update', itemId: 'ITEM_1', row: changed, set: { waitingOn: 'author', reason: 'changes-requested' } },
    ]);
});

test('an item whose pull request is no longer open is archived', () => {
    assert.deepEqual(planProjectChanges([row()], [item(), item({ id: 'ITEM_2', pullRequestId: 'PR_2' })]), [{ kind: 'archive', itemId: 'ITEM_2' }]);
});

test('resolveSchema names the field it cannot find', () => {
    assert.throws(() => resolveSchema('PROJECT', []), /SINGLE_SELECT field named "Waiting on"/);
    assert.throws(
        () => resolveSchema('PROJECT', [
            { id: 'F_WAITING', name: 'Waiting on', dataType: 'SINGLE_SELECT' },
            { id: 'F_REASON', name: 'Reason', dataType: 'SINGLE_SELECT' },
            { id: 'F_SINCE', name: 'Waiting since', dataType: 'TEXT' },
        ]),
        /DATE field named "Waiting since"/,
    );
});

test('toProjectItems keeps only this repository and reads the field values by name', () => {
    const items = toProjectItems(
        [
            {
                id: 'ITEM_1',
                content: { id: 'PR_1', repository: { nameWithOwner: 'shopware/shopware' } },
                fieldValues: {
                    nodes: [
                        { name: 'author', field: { name: 'Waiting on' } },
                        { date: '2026-09-01', field: { name: 'Waiting since' } },
                        { name: 'Todo', field: { name: 'Status' } },
                        {},
                    ],
                },
            },
            { id: 'ITEM_2', content: { id: 'PR_9', repository: { nameWithOwner: 'shopware/SwagCommercial' } }, fieldValues: { nodes: [] } },
            { id: 'ITEM_3', content: {}, fieldValues: { nodes: [] } },
        ],
        'shopware/shopware',
    );

    assert.deepEqual(items, [{ id: 'ITEM_1', pullRequestId: 'PR_1', values: { waitingOn: 'author', since: '2026-09-01' } }]);
});

test('buildFieldMutation sets every field in one request and skips an option the project lacks', () => {
    const warnings: string[] = [];
    const mutation = buildFieldMutation(schema, 'ITEM_1', { waitingOn: 'author', reason: 'our-turn', since: '2026-09-01' }, { warning: (message) => warnings.push(message) });

    assert.ok(mutation);
    assert.match(mutation.query, /waitingOn: updateProjectV2ItemFieldValue/);
    assert.match(mutation.query, /since: updateProjectV2ItemFieldValue/);
    assert.doesNotMatch(mutation.query, /reason:/);
    assert.deepEqual(mutation.variables, {
        projectId: 'PROJECT',
        itemId: 'ITEM_1',
        waitingOn: 'O_AUTHOR',
        waitingOnField: 'F_WAITING',
        since: '2026-09-01',
        sinceField: 'F_SINCE',
    });
    assert.deepEqual(warnings, ['The "Reason" field has no option "our-turn"; add it to the project.']);
});

test('buildFieldMutation has nothing to send when every value is missing an option', () => {
    assert.equal(buildFieldMutation(schema, 'ITEM_1', { reason: 'our-turn' }, { warning() {} }), undefined);
});

test('applyProjectChanges sets the fields on the item it just added and carries on past a failure', async () => {
    const calls: Record<string, unknown>[] = [];
    const github = {
        graphql: async <T>(query: string, variables: Record<string, unknown>): Promise<T> => {
            calls.push(variables);
            if (variables.itemId === 'ITEM_BROKEN') {
                throw new Error('Could not resolve to a node');
            }

            return (query.includes('addProjectV2ItemById') ? { addProjectV2ItemById: { item: { id: 'ITEM_NEW' } } } : {}) as T;
        },
    };
    const errors: string[] = [];
    const core = { info() {}, warning() {}, error: (message: string) => errors.push(message) };

    const failed = await applyProjectChanges(
        github,
        core,
        schema,
        [
            { kind: 'archive', itemId: 'ITEM_BROKEN' },
            { kind: 'add', row: row(), set: { waitingOn: 'shopware', since: '2026-09-01' } },
        ],
        0,
    );

    assert.deepEqual(failed, ['item ITEM_BROKEN']);
    assert.equal(errors.length, 1);
    assert.deepEqual(calls.slice(1).map((variables) => variables.itemId ?? variables.contentId), ['PR_1', 'ITEM_NEW']);
});
