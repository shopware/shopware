import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    type BoardItem,
    type GraphqlClient,
    PROJECT_AUTOMATION_LOGIN,
    type TriageBoard,
    evaluateItem,
    itemQuery,
    moveCopiedStatusesToTriage,
    parseBoards,
} from './board-to-triage.ts';

const board: TriageBoard = { number: 27, triageStatus: 'To Triage', copiedStatuses: ['Backlog'] };
const since = new Date('2026-11-20T00:00:00Z');

const item = (overrides: Partial<BoardItem> = {}, issue: Partial<NonNullable<BoardItem['issue']>> = {}): BoardItem => ({
    id: 'ITEM_1',
    addedAt: '2026-11-26T08:31:42Z',
    status: 'Backlog',
    issue: {
        reference: 'shopware/shopware#23439',
        isOpen: true,
        statusChanges: [{ actor: PROJECT_AUTOMATION_LOGIN, projectNumber: 27, status: 'Backlog' }],
        ...issue,
    },
    ...overrides,
});

test('a new open issue the automation put into a copied column moves to triage', () => {
    assert.deepEqual(evaluateItem(item(), board, since), { action: 'move' });
});

test('a status a person chose on this board keeps the item where it is', () => {
    const statusChanges = [
        { actor: PROJECT_AUTOMATION_LOGIN, projectNumber: 27, status: 'Backlog' },
        { actor: 'gweiermann', projectNumber: 27, status: 'Backlog' },
    ];

    assert.deepEqual(evaluateItem(item({}, { statusChanges }), board, since), {
        action: 'skip',
        reason: 'status was set by gweiermann',
    });
});

test('the sweep’s own earlier write counts as a chosen status, so an item moves only once', () => {
    const statusChanges = [
        { actor: PROJECT_AUTOMATION_LOGIN, projectNumber: 27, status: 'Backlog' },
        { actor: 'shopware-octo-sts-app', projectNumber: 27, status: 'To Triage' },
    ];

    assert.equal(evaluateItem(item({}, { statusChanges }), board, since).action, 'skip');
});

test('status changes on other boards do not count', () => {
    const statusChanges = [
        { actor: PROJECT_AUTOMATION_LOGIN, projectNumber: 27, status: 'Backlog' },
        { actor: 'gweiermann', projectNumber: 58, status: 'Todo' },
    ];

    assert.deepEqual(evaluateItem(item({}, { statusChanges }), board, since), { action: 'move' });
});

test('an item that started in triage stays where the automation moved it later', () => {
    const statusChanges = [
        { actor: PROJECT_AUTOMATION_LOGIN, projectNumber: 27, status: 'To Triage' },
        { actor: PROJECT_AUTOMATION_LOGIN, projectNumber: 27, status: 'Done' },
        { actor: PROJECT_AUTOMATION_LOGIN, projectNumber: 27, status: 'Backlog' },
    ];

    assert.deepEqual(evaluateItem(item({}, { statusChanges }), board, since), {
        action: 'skip',
        reason: 'item started in To Triage',
    });
    assert.equal(evaluateItem(item({}, { statusChanges: [] }), board, since).action, 'skip');
});

test('items outside the rule are skipped', () => {
    assert.equal(evaluateItem(item({ issue: null }), board, since).action, 'skip');
    assert.equal(evaluateItem(item({}, { isOpen: false }), board, since).action, 'skip');
    assert.equal(evaluateItem(item({ status: 'ToDo' }), board, since).action, 'skip');
    assert.equal(evaluateItem(item({ status: null }), board, since).action, 'skip');
    assert.equal(evaluateItem(item({ addedAt: '2026-11-01T00:00:00Z' }), board, since).action, 'skip');
});

test('the item search asks only for open issues in the copied columns', () => {
    assert.equal(itemQuery(board), 'is:issue is:open status:"Backlog"');
    assert.equal(
        itemQuery({ ...board, copiedStatuses: ['Backlog', 'In progress'] }),
        'is:issue is:open status:"Backlog","In progress"',
    );
});

test('the board list must be well formed', () => {
    assert.deepEqual(parseBoards(JSON.stringify([board])), [board]);
    assert.deepEqual(parseBoards(undefined), []);
    assert.throws(() => parseBoards('{"number":27}'), /TRIAGE_BOARDS/);
    assert.throws(() => parseBoards('[{"number":27,"triageStatus":"To Triage"}]'), /TRIAGE_BOARDS/);
});

type Call = { query: string; variables: Record<string, unknown> };

function fakeGithub(pages: unknown[][], options = [{ id: 'OPT_TRIAGE', name: 'To Triage' }]) {
    const calls: Call[] = [];
    const github: GraphqlClient = {
        async graphql<T>(query: string, variables: Record<string, unknown>): Promise<T> {
            calls.push({ query, variables });
            if (query.includes('updateProjectV2ItemFieldValue')) {
                return {} as T;
            }

            const page = variables.after === null ? 0 : Number(variables.after);
            return {
                organization: {
                    projectV2: {
                        id: 'PROJECT_27',
                        field: { id: 'FIELD_STATUS', options },
                        items: {
                            pageInfo: {
                                hasNextPage: page + 1 < pages.length,
                                endCursor: String(page + 1),
                            },
                            nodes: pages[page],
                        },
                    },
                },
            } as T;
        },
    };

    return { github, calls, writes: () => calls.filter((call) => call.query.includes('updateProjectV2ItemFieldValue')) };
}

const node = (id: string, number: number, actors: string[]) => ({
    id,
    createdAt: '2026-11-26T08:31:42Z',
    fieldValueByName: { name: 'Backlog' },
    content: {
        number,
        state: 'OPEN',
        repository: { nameWithOwner: 'shopware/shopware' },
        timelineItems: { nodes: actors.map((login) => ({ actor: { login }, project: { number: 27 }, status: 'Backlog' })) },
    },
});

const silent = { info: () => {} };
const now = new Date('2026-11-27T00:00:00Z');

test('the sweep sets the triage option on every item the rule picks, across pages', async () => {
    const { github, writes } = fakeGithub([
        [node('ITEM_A', 1, [PROJECT_AUTOMATION_LOGIN]), node('ITEM_B', 2, [PROJECT_AUTOMATION_LOGIN, 'someone'])],
        [
            node('ITEM_C', 3, [PROJECT_AUTOMATION_LOGIN]),
            { id: 'ITEM_PR', createdAt: '2026-11-26T00:00:00Z', fieldValueByName: { name: 'Backlog' }, content: {} },
        ],
    ]);

    const moved = await moveCopiedStatusesToTriage(
        { github, core: silent },
        { org: 'shopware', boards: [board], dryRun: false, lookbackDays: 7, now },
    );

    assert.deepEqual(moved, ['shopware/shopware#1', 'shopware/shopware#3']);
    assert.deepEqual(
        writes().map((call) => call.variables),
        [
            { project: 'PROJECT_27', item: 'ITEM_A', field: 'FIELD_STATUS', option: 'OPT_TRIAGE' },
            { project: 'PROJECT_27', item: 'ITEM_C', field: 'FIELD_STATUS', option: 'OPT_TRIAGE' },
        ],
    );
});

test('a dry run reports the moves and writes nothing', async () => {
    const { github, writes } = fakeGithub([[node('ITEM_A', 1, [PROJECT_AUTOMATION_LOGIN])]]);

    const moved = await moveCopiedStatusesToTriage(
        { github, core: silent },
        { org: 'shopware', boards: [board], dryRun: true, lookbackDays: 7, now },
    );

    assert.deepEqual(moved, ['shopware/shopware#1']);
    assert.equal(writes().length, 0);
});

test('a board without the triage option fails the run instead of skipping it', async () => {
    const { github } = fakeGithub([[]], [{ id: 'OPT_BACKLOG', name: 'Backlog' }]);

    await assert.rejects(
        moveCopiedStatusesToTriage(
            { github, core: silent },
            { org: 'shopware', boards: [board], dryRun: false, lookbackDays: 7, now },
        ),
        /no Status option "To Triage"/,
    );
});
