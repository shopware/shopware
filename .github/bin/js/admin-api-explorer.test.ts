import assert from 'node:assert/strict';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';
import {
    codeSpan,
    COMMENT_MARKER,
    type CommentData,
    hasCommentArtifact,
    type IssueComment,
    MAX_DATA_BYTES,
    MAX_LIST_ENTRIES,
    matchRunToPullRequest,
    type PullRequest,
    readRelayPayload,
    relayComment,
    type RelayClient,
    renderComment,
    validateCommentData,
    verifyReport,
    type WorkflowRun,
} from './admin-api-explorer.ts';

const BASE_SHA = 'a'.repeat(40);
const HEAD_SHA = 'b'.repeat(40);
const expected = { base: BASE_SHA, head: HEAD_SHA };
const RUN_URL = 'https://github.com/shopware/shopware/actions/runs/99';

const side = { components: 412, objectNodes: 1873, diagnostics: 0 };
const summary = (overrides: Record<string, unknown> = {}) => ({
    total: 0,
    bySeverity: {},
    hasBreaking: false,
    base: { ref: 'trunk', commit: BASE_SHA, ...side },
    head: { ref: 'WORKTREE', commit: HEAD_SHA, ...side },
    ...overrides,
});

const entry = (overrides: Record<string, unknown> = {}) => ({ kind: 'prop-removed', owner: 'sw-button', member: 'variant', reason: 'The prop was removed', ...overrides });

const commentData = (overrides: Record<string, unknown> = {}): CommentData =>
    ({
        version: 1,
        base: { ref: 'trunk', commit: BASE_SHA },
        head: { ref: 'WORKTREE', commit: HEAD_SHA },
        counts: { breaking: 1, potentiallyBreaking: 1, compatible: 2, added: 3, newlyDeprecated: 0, removedDeprecated: 0, private: 4 },
        breaking: [entry()],
        potentiallyBreaking: [entry({ kind: 'event-payload-changed', member: 'click' })],
        omitted: { breaking: 0, potentiallyBreaking: 0 },
        ...overrides,
    }) as CommentData;

function reportDir(overrides: { summary?: unknown; comment?: string; data?: unknown; skip?: string[] } = {}): string {
    const dir = mkdtempSync(join(tmpdir(), 'api-explorer-report-'));
    const files: Record<string, string> = {
        'index.html': '<!doctype html><title>report</title>',
        'comment.md': overrides.comment ?? `${COMMENT_MARKER}\n## Admin API Explorer\nNo changes.\n`,
        'comment-data.json': JSON.stringify(overrides.data ?? commentData()),
        'diff.json': '{"changes":[]}',
        'summary.json': JSON.stringify(overrides.summary ?? summary()),
    };
    for (const [name, content] of Object.entries(files)) {
        if (!overrides.skip?.includes(name)) {
            writeFileSync(join(dir, name), content);
        }
    }
    return dir;
}

function relayDir(number: string, data: unknown): string {
    const dir = mkdtempSync(join(tmpdir(), 'api-explorer-relay-'));
    writeFileSync(join(dir, 'pr-number.txt'), number);
    writeFileSync(join(dir, 'comment-data.json'), typeof data === 'string' ? data : JSON.stringify(data));
    return dir;
}

test('a complete report of the expected commits passes the guard, with and without breaking changes', () => {
    assert.deepEqual(verifyReport(reportDir(), 0, expected), []);
    assert.deepEqual(verifyReport(reportDir({ summary: summary({ hasBreaking: true }) }), 3, expected), []);
});

test('the guard fails for any exit code other than done or breaking', () => {
    assert.match(verifyReport(reportDir(), 1, expected)[0], /exited with 1/);
});

test('the guard fails when a report file is missing', () => {
    const problems = verifyReport(reportDir({ skip: ['summary.json', 'index.html', 'comment-data.json'] }), 0, expected);
    assert.equal(problems.length, 3);
    assert.match(problems[0], /^index\.html is missing/);
    assert.match(problems[1], /^comment-data\.json is missing/);
    assert.match(problems[2], /^summary\.json is missing/);
});

test('the guard fails on an empty extraction, where only the root would have been an object node', () => {
    const empty = { ...side, components: 0, objectNodes: 0 };
    const problems = verifyReport(reportDir({ summary: summary({ base: { ref: 'trunk', commit: BASE_SHA, ...empty }, head: { ref: 'WORKTREE', commit: HEAD_SHA, ...empty } }) }), 0, expected);

    assert.deepEqual(
        problems.map((problem) => problem.split(' is ')[0]),
        ['summary.json base.components', 'summary.json base.objectNodes', 'summary.json head.components', 'summary.json head.objectNodes'],
    );
});

test('the guard fails when one side extracted components but no object nodes', () => {
    const problems = verifyReport(reportDir({ summary: summary({ head: { ref: 'WORKTREE', commit: HEAD_SHA, ...side, objectNodes: 0 } }) }), 0, expected);
    assert.equal(problems.length, 1);
    assert.match(problems[0], /head\.objectNodes is 0/);
});

test('the guard fails when the report compared other commits than the workflow checked out', () => {
    assert.match(verifyReport(reportDir(), 0, { base: 'c'.repeat(40), head: HEAD_SHA })[0], /base\.commit is "a{40}", but the workflow checked out c{40}/);
    assert.match(verifyReport(reportDir(), 0, { base: BASE_SHA, head: 'c'.repeat(40) })[0], /head\.commit is "b{40}", but the workflow checked out c{40}/);
});

test('the guard fails when a side is absent from the summary', () => {
    assert.equal(verifyReport(reportDir({ summary: summary({ head: undefined }) }), 0, expected).length, 3);
});

test('the guard fails when the exit code contradicts hasBreaking', () => {
    assert.match(verifyReport(reportDir({ summary: summary({ hasBreaking: true }) }), 0, expected)[0], /hasBreaking is true, but the command exited with 0/);
    assert.match(verifyReport(reportDir(), 3, expected)[0], /hasBreaking is false, but the command exited with 3/);
});

test('the guard fails on an unmarked comment or comment data the relay would reject', () => {
    assert.match(verifyReport(reportDir({ comment: '## Admin API Explorer\n' }), 0, expected)[0], /does not start with/);
    assert.match(verifyReport(reportDir({ data: commentData({ version: 3 }) }), 0, expected)[0], /^comment-data\.json: data\.version/);
});

test('well-formed comment data is accepted', () => {
    assert.deepEqual(validateCommentData(commentData()), []);
    assert.deepEqual(validateCommentData(commentData({ breaking: [], potentiallyBreaking: [] })), []);
});

test('comment data with a wrong version, sha or count is rejected', () => {
    assert.match(validateCommentData(commentData({ version: '1' })).join(), /data\.version/);
    assert.match(validateCommentData(commentData({ base: { ref: 'trunk', commit: 'abc' } })).join(), /data\.base\.commit is not a 40 character hex sha/);
    assert.match(validateCommentData(commentData({ head: { ref: 'x', commit: 'B'.repeat(40) } })).join(), /data\.head\.commit/);

    for (const count of [-1, 1.5, '3', null, Number.MAX_VALUE]) {
        const counts = { ...commentData().counts, breaking: count };
        assert.match(validateCommentData(commentData({ counts })).join(), /data\.counts\.breaking is not a non-negative integer/, String(count));
    }
    assert.match(validateCommentData(commentData({ omitted: { breaking: -1, potentiallyBreaking: 0 } })).join(), /data\.omitted\.breaking/);
});

test('comment data with unknown or missing keys is rejected', () => {
    assert.match(validateCommentData({ ...commentData(), body: '# hi' }).join(), /data\.body is not allowed/);
    assert.match(validateCommentData(commentData({ breaking: [{ ...entry(), url: 'https://evil.example' }] })).join(), /data\.breaking\[0\]\.url is not allowed/);
    assert.match(validateCommentData(commentData({ counts: { ...commentData().counts, extra: 1 } })).join(), /data\.counts\.extra is not allowed/);
    const { reason: _reason, ...withoutReason } = entry();
    assert.match(validateCommentData(commentData({ breaking: [withoutReason] })).join(), /data\.breaking\[0\]\.reason is missing/);
});

test('comment data with wrong types is rejected', () => {
    assert.deepEqual(validateCommentData([]), ['comment data is not an object']);
    assert.deepEqual(validateCommentData('<!-- admin-api-explorer-report -->'), ['comment data is not an object']);
    assert.match(validateCommentData(commentData({ breaking: {} })).join(), /data\.breaking is not an array/);
    assert.match(validateCommentData(commentData({ breaking: ['text'] })).join(), /data\.breaking\[0\] is not an object/);
    assert.match(validateCommentData(commentData({ breaking: [entry({ owner: 42 })] })).join(), /data\.breaking\[0\]\.owner is not a string/);
});

test('comment data with overlong strings, control characters or too many entries is rejected', () => {
    assert.match(validateCommentData(commentData({ base: { ref: 'x'.repeat(101), commit: BASE_SHA } })).join(), /data\.base\.ref must have 1 to 100 characters/);
    assert.match(validateCommentData(commentData({ breaking: [entry({ kind: 'k'.repeat(41) })] })).join(), /kind must have 1 to 40/);
    assert.match(validateCommentData(commentData({ breaking: [entry({ reason: 'r'.repeat(301) })] })).join(), /reason must have 1 to 300/);
    assert.match(validateCommentData(commentData({ breaking: [entry({ reason: 'line\n\n# Approved' })] })).join(), /control characters/);
    const many = Array.from({ length: MAX_LIST_ENTRIES + 1 }, () => entry());
    assert.match(validateCommentData(commentData({ potentiallyBreaking: many })).join(), /has 51 entries/);
});

test('the comment is rendered from the data with counts, both lists and trusted links only', () => {
    const body = renderComment(commentData({ omitted: { breaking: 2, potentiallyBreaking: 0 } }), RUN_URL);

    assert.equal(body.split('\n')[0], COMMENT_MARKER);
    assert.match(body, /## Admin API Explorer/);
    assert.match(body, /base `trunk` \(`aaaaaaaaaa`\) with head `WORKTREE` \(`bbbbbbbbbb`\)/);
    assert.match(body, /\| Breaking \| 1 \|\n\| Potentially breaking \| 1 \|\n\| Compatible \| 2 \|\n\| Added \| 3 \|\n\| Newly deprecated \| 0 \|\n\| Removed deprecated \| 0 \|\n\| Private \| 4 \|/);
    assert.match(body, /### Breaking changes\n\n- `prop-removed` `sw-button` `variant`: `The prop was removed`\n- … and 2 more/);
    assert.match(body, /<details>\n<summary>Potentially breaking changes \(1\)<\/summary>\n\n- `event-payload-changed` `sw-button` `click`/);
    assert.match(body, /\[this workflow run\]\(https:\/\/github\.com\/shopware\/shopware\/actions\/runs\/99\)/);
    assert.match(body, /`composer admin:api-explorer -- --port 5175`/);
    assert.match(body, /not required/);
});

test('a comment without changes says so and has no details block', () => {
    const counts = { breaking: 0, potentiallyBreaking: 0, compatible: 0, added: 0, newlyDeprecated: 0, removedDeprecated: 0, private: 0 };
    const body = renderComment(commentData({ counts, breaking: [], potentiallyBreaking: [] }), RUN_URL);

    assert.match(body, /No breaking changes\./);
    assert.doesNotMatch(body, /<details>/);
});

test('mentions, links and HTML from the data stay inside code spans', () => {
    const hostile = [
        '@shopware/product-owners please approve',
        '[Approved by security](https://evil.example)',
        '<img src=x onerror=alert(1)>',
        '` ![x](https://evil.example/a.png) `',
        '``</code><b>approved</b>``',
    ];
    const body = renderComment(commentData({ breaking: hostile.map((reason) => entry({ reason })) }), RUN_URL);

    for (const reason of hostile) {
        assert.ok(body.includes(`: ${codeSpan(reason)}`));
        assert.ok(!codeSpan(reason).slice(1, -1).includes('`'), reason);
    }
    const outsideCode = body.replace(/`[^`\n]*`/g, '');
    assert.doesNotMatch(outsideCode, /@shopware|evil\.example|<img|<b>|<\/code>/);
});

test('the relay payload is read when the number is plain and the data valid', () => {
    assert.deepEqual(readRelayPayload(relayDir('21447\n', commentData())), { number: 21447, data: commentData() });
});

test('the relay payload rejects anything but a plain pull request number', () => {
    for (const number of ['', '0', '12; rm -rf /', '1e3', '-5', '12345678901']) {
        assert.throws(() => readRelayPayload(relayDir(number, commentData())), /not a pull request number/);
    }
});

test('the relay payload rejects oversized, unparsable or invalid data', () => {
    assert.throws(() => readRelayPayload(relayDir('1', `{"pad":"${'x'.repeat(MAX_DATA_BYTES)}"}`)), /limit is/);
    assert.throws(() => readRelayPayload(relayDir('1', `${COMMENT_MARKER}\n# Approved`)), /not valid JSON/);
    assert.throws(() => readRelayPayload(relayDir('1', { ...commentData(), markdown: 'x' })), /data\.markdown is not allowed/);
});

const run = (overrides: Partial<WorkflowRun> = {}): WorkflowRun => ({
    id: 99,
    html_url: RUN_URL,
    head_sha: 'head-1',
    head_repository: { full_name: 'someone/shopware' },
    pull_requests: [],
    ...overrides,
});

const pullRequest = (overrides: Partial<PullRequest> = {}): PullRequest => ({
    number: 7,
    state: 'open',
    head: { sha: 'head-1', repo: { full_name: 'someone/shopware' } },
    ...overrides,
});

test('a fork run matches its pull request through the head commit', () => {
    assert.equal(matchRunToPullRequest(run(), pullRequest()), 'match');
});

test('a run whose pull request moved on is stale', () => {
    assert.equal(matchRunToPullRequest(run(), pullRequest({ head: { sha: 'head-2', repo: { full_name: 'someone/shopware' } } })), 'stale');
});

test('a number that names another pull request is foreign', () => {
    assert.equal(matchRunToPullRequest(run({ pull_requests: [{ number: 8 }] }), pullRequest()), 'foreign');
    assert.equal(matchRunToPullRequest(run(), pullRequest({ head: { sha: 'head-1', repo: { full_name: 'other/shopware' } } })), 'foreign');
});

type Call = { method: string; options: Record<string, unknown> };

function fakeGithub(pr: PullRequest, comments: IssueComment[], artifacts: { name: string }[] = []): { github: RelayClient; calls: Call[] } {
    const calls: Call[] = [];
    const listComments = Symbol('listComments');
    const listWorkflowRunArtifacts = Symbol('listWorkflowRunArtifacts');
    const github = {
        paginate: async (route: unknown) => (route === listComments ? comments : route === listWorkflowRunArtifacts ? artifacts : assert.fail('unexpected route')),
        rest: {
            actions: { listWorkflowRunArtifacts },
            pulls: { get: async () => ({ data: pr }) },
            issues: {
                listComments,
                createComment: async (options: Record<string, unknown>) => calls.push({ method: 'create', options }),
                updateComment: async (options: Record<string, unknown>) => calls.push({ method: 'update', options }),
            },
        },
    } as unknown as RelayClient;
    return { github, calls };
}

const core = { info: () => {}, notice: () => {} };
const context = { repo: { owner: 'shopware', repo: 'shopware' }, payload: { workflow_run: run() } };
const body = renderComment(commentData(), RUN_URL);

test('the relay creates the rendered comment when the pull request has none', async () => {
    const { github, calls } = fakeGithub(pullRequest(), [{ id: 1, body: 'LGTM', user: { login: 'someone' } }]);

    assert.equal(await relayComment({ github, core, context }, relayDir('7', commentData())), 'created');
    assert.deepEqual(calls, [{ method: 'create', options: { owner: 'shopware', repo: 'shopware', issue_number: 7, body } }]);
});

test('the relay updates its own comment instead of adding a second one', async () => {
    const { github, calls } = fakeGithub(pullRequest(), [
        { id: 1, body: `${COMMENT_MARKER}\ncopied by a person`, user: { login: 'someone' } },
        { id: 2, body: `${COMMENT_MARKER}\nold`, user: { login: 'github-actions[bot]' } },
    ]);

    assert.equal(await relayComment({ github, core, context }, relayDir('7', commentData())), 'updated');
    assert.deepEqual(calls, [{ method: 'update', options: { owner: 'shopware', repo: 'shopware', comment_id: 2, body } }]);
});

test('the relay does not comment for a stale run or a closed pull request', async () => {
    const stale = fakeGithub(pullRequest({ head: { sha: 'head-2', repo: { full_name: 'someone/shopware' } } }), []);
    assert.equal(await relayComment({ github: stale.github, core, context }, relayDir('7', commentData())), 'skipped');
    assert.deepEqual(stale.calls, []);

    const closed = fakeGithub(pullRequest({ state: 'closed' }), []);
    assert.equal(await relayComment({ github: closed.github, core, context }, relayDir('7', commentData())), 'skipped');
    assert.deepEqual(closed.calls, []);
});

test('the relay refuses a number that belongs to another pull request, and invalid data', async () => {
    const foreign = fakeGithub(pullRequest({ head: { sha: 'head-1', repo: { full_name: 'other/shopware' } } }), []);
    await assert.rejects(relayComment({ github: foreign.github, core, context }, relayDir('7', commentData())), /refusing to comment/);
    assert.deepEqual(foreign.calls, []);

    const invalid = fakeGithub(pullRequest(), []);
    await assert.rejects(relayComment({ github: invalid.github, core, context }, relayDir('7', commentData({ version: 3 }))), /rejected/);
    assert.deepEqual(invalid.calls, []);
});

test('the comment artifact is found by name on the triggering run', async () => {
    assert.equal(await hasCommentArtifact({ github: fakeGithub(pullRequest(), [], [{ name: 'api-explorer-comment' }]).github, context }, 'api-explorer-comment'), true);
    assert.equal(await hasCommentArtifact({ github: fakeGithub(pullRequest(), [], [{ name: 'api-explorer-report' }]).github, context }, 'api-explorer-comment'), false);
});

const privateCounts = { breaking: 12, potentiallyBreaking: 9, compatible: 54, added: 61, newlyDeprecated: 3, removedDeprecated: 0 };
const commentDataV2 = (overrides: Record<string, unknown> = {}): CommentData =>
    commentData({ version: 2, counts: { ...commentData().counts, private: 139 }, privateCounts, ...overrides });

test('version 2 comment data with private counts adding up to counts.private is accepted', () => {
    assert.deepEqual(validateCommentData(commentDataV2()), []);
    assert.deepEqual(validateCommentData(commentDataV2({ counts: { ...commentData().counts, private: 0 }, privateCounts: { ...privateCounts, breaking: 0, potentiallyBreaking: 0, compatible: 0, added: 0, newlyDeprecated: 0 } })), []);
});

test('version 1 comment data is still accepted, but without private counts', () => {
    assert.deepEqual(validateCommentData(commentData({ version: 1 })), []);
    assert.match(validateCommentData(commentData({ version: 1, privateCounts })).join(), /data\.privateCounts is not allowed/);
});

test('version 2 comment data with wrong private counts is rejected', () => {
    assert.match(validateCommentData(commentDataV2({ privateCounts: { ...privateCounts, added: 60 } })).join(), /privateCounts add up to 138, but data\.counts\.private is 139/);
    assert.match(validateCommentData(commentDataV2({ privateCounts: undefined })).join(), /data\.privateCounts/);
    const { added: _added, ...withoutAdded } = privateCounts;
    assert.match(validateCommentData(commentDataV2({ privateCounts: withoutAdded })).join(), /data\.privateCounts\.added is missing/);
    assert.match(validateCommentData(commentDataV2({ privateCounts: { ...privateCounts, internal: 0 } })).join(), /data\.privateCounts\.internal is not allowed/);
    for (const count of [1.5, '12', -1, null]) {
        assert.match(validateCommentData(commentDataV2({ privateCounts: { ...privateCounts, breaking: count } })).join(), /data\.privateCounts\.breaking is not a non-negative integer/, String(count));
    }
    assert.match(validateCommentData(commentDataV2({ privateCounts: [] })).join(), /data\.privateCounts is not an object/);
    assert.match(validateCommentData(commentData({ version: 3 })).join(), /expected 1 or 2/);
});

test('the private API line is rendered only for version 2 with private changes', () => {
    const line = '**Private API** (not a public contract, never turns this check red): 12 breaking · 9 potentially breaking · 54 compatible · 61 added · 3 newly deprecated · 0 removed (was deprecated)';

    const body = renderComment(commentDataV2(), RUN_URL);
    assert.equal(body.split('\n').filter((candidate) => candidate.startsWith('**Private API**')).length, 1);
    assert.match(body, new RegExp(`\\| Private \\| 139 \\|\\n\\n${line.replace(/[*()|]/g, '\\$&')}\\n`));

    const zero = { breaking: 0, potentiallyBreaking: 0, compatible: 0, added: 0, newlyDeprecated: 0, removedDeprecated: 0 };
    assert.doesNotMatch(renderComment(commentDataV2({ counts: { ...commentData().counts, private: 0 }, privateCounts: zero }), RUN_URL), /Private API/);
    assert.doesNotMatch(renderComment(commentData(), RUN_URL), /Private API/);
});

test('the guard accepts version 1 and version 2 comment data', () => {
    assert.deepEqual(verifyReport(reportDir({ data: commentData() }), 0, expected), []);
    assert.deepEqual(verifyReport(reportDir({ data: commentDataV2() }), 0, expected), []);
    assert.match(verifyReport(reportDir({ data: commentDataV2({ privateCounts: { ...privateCounts, added: 0 } }) }), 0, expected)[0], /^comment-data\.json: data\.privateCounts add up to/);
});
