import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';
import {
    COMMENT_MARKER,
    buildComment,
    collectAgentInput,
    isDryRun,
    neutraliseMentions,
    parseAudiences,
    postComment,
    resolvePullRequestNumber,
    shouldCheck,
} from './docs-impact.ts';

type Call = { method: string; params: Record<string, unknown> };

function fakeGitHub(options: {
    associated?: Array<{ number: number; merged_at: string | null; base: { ref: string } }>;
    comments?: Array<{ id: number; body?: string }>;
    diffStatus?: number;
} = {}) {
    const calls: Call[] = [];
    const record = (method: string, result: unknown = {}) => async (params: Record<string, unknown>) => {
        calls.push({ method, params });
        return result;
    };
    const github = {
        rest: {
            repos: {
                listPullRequestsAssociatedWithCommit: record('listPullRequestsAssociatedWithCommit', { data: options.associated ?? [] }),
            },
            pulls: {
                get: async (params: Record<string, unknown>) => {
                    calls.push({ method: 'pulls.get', params });
                    if (params.mediaType) {
                        if (options.diffStatus) {
                            throw Object.assign(new Error('diff'), { status: options.diffStatus });
                        }
                        return { data: 'diff --git a/x b/x' };
                    }
                    return {
                        data: {
                            number: params.pull_number,
                            title: 'Title',
                            body: null,
                            html_url: 'https://github.com/shopware/shopware/pull/7',
                            user: { login: 'alice', avatar_url: 'x' },
                            labels: [{ name: 'domain/inventory', color: 'fff' }],
                            merged_at: '2026-10-01T00:00:00Z',
                            base: { ref: 'trunk', repo: { full_name: 'shopware/shopware' } },
                            head: { ref: 'feature', repo: { full_name: 'alice/shopware' } },
                        },
                    };
                },
                listFiles: Symbol('listFiles'),
            },
            issues: {
                listComments: Symbol('listComments'),
                createComment: record('createComment'),
                updateComment: record('updateComment'),
            },
        },
        paginate: async (method: unknown) => {
            if (method === github.rest.issues.listComments) {
                return options.comments ?? [];
            }
            return [{ filename: 'src/a.php', status: 'modified', additions: 1, deletions: 1, blob_url: 'x', sha: 'y', patch: '@@ -1 +1 @@' }];
        },
    };

    return { github: github as never, calls };
}

function fakeCore() {
    const messages: string[] = [];
    return {
        core: {
            info: (m: string) => messages.push(m),
            warning: (m: string) => messages.push(`warning: ${m}`),
            setFailed: (m: string) => messages.push(`failed: ${m}`),
        },
        messages,
    };
}

const pushContext = (inputs?: { pr_number?: string; dry_run?: string | boolean }) => ({
    repo: { owner: 'shopware', repo: 'shopware' },
    sha: 'abc123',
    eventName: inputs ? 'workflow_dispatch' : 'push',
    payload: inputs ? { inputs } : {},
});

test('shouldCheck skips test, ci, chore, docs and refactor merges', () => {
    for (const message of [
        'test: cover the cart (#1)',
        'ci(acceptance): shard by suite (#2)',
        'chore: bump deps (#3)',
        'docs(admin): fix typo (#4)',
        'refactor(core): extract helper (#5)\n\nbody',
    ]) {
        assert.equal(shouldCheck('push', message), false, message);
    }
});

test('shouldCheck keeps fix, feat, perf, breaking and untyped merges', () => {
    for (const message of [
        'fix: restore price (#1)',
        'feat(storefront): add title (#2)',
        'perf(checkout): load cart once (#3)',
        'refactor(core)!: rename event (#4)',
        'chore!: drop PHP 8.2 (#5)',
        'Revert "test: cover the cart" (#6)',
        'testing: something (#7)',
        '',
    ]) {
        assert.equal(shouldCheck('push', message), true, message);
    }
});

test('shouldCheck always runs a manual dispatch', () => {
    assert.equal(shouldCheck('workflow_dispatch', 'chore: bump deps'), true);
});

test('parseAudiences accepts known audiences and dedupes', () => {
    assert.deepEqual(parseAudiences('end-user, developer,end-user'), ['end-user', 'developer']);
});

test('parseAudiences rejects unknown and empty audiences', () => {
    assert.throws(() => parseAudiences('end-user,marketing'), /Unknown audience\(s\): marketing/);
    assert.throws(() => parseAudiences(' , '), /No audience given/);
});

test('neutraliseMentions wraps users and teams but leaves emails and code alone', () => {
    assert.equal(
        neutraliseMentions('ping @alice and @shopware/core, mail a@b.com, `@keep`'),
        'ping `@alice` and `@shopware/core`, mail a@b.com, `@keep`',
    );
});

test('buildComment writes a neutral note without any mention', () => {
    const comment = buildComment('New event @someone added, see @shopware/po-docs');

    assert.ok(comment.startsWith(`${COMMENT_MARKER}\n### Docs candidate`));
    assert.ok(comment.includes('No action required.'));
    assert.ok(comment.includes('`@someone`'));
    assert.ok(comment.includes('`@shopware/po-docs`'));
    assert.equal(/(^|[^`])@[\w/-]+/.test(comment.replace(/`@[^`]+`/g, '')), false);
});

test('buildComment truncates an oversized summary', () => {
    const comment = buildComment('x'.repeat(30000));

    assert.ok(comment.length < 21000);
    assert.ok(comment.endsWith('_(truncated)_'));
});

test('resolvePullRequestNumber prefers the dispatch input', async () => {
    const { github, calls } = fakeGitHub();

    assert.equal(await resolvePullRequestNumber({ github, context: pushContext({ pr_number: '42' }) }), 42);
    assert.equal(calls.length, 0);
});

test('resolvePullRequestNumber rejects an invalid dispatch input', async () => {
    const { github } = fakeGitHub();

    await assert.rejects(resolvePullRequestNumber({ github, context: pushContext({ pr_number: '4x' }) }), /Invalid pr_number/);
});

test('resolvePullRequestNumber picks the merged trunk pull request of the commit', async () => {
    const { github } = fakeGitHub({
        associated: [
            { number: 1, merged_at: null, base: { ref: 'trunk' } },
            { number: 2, merged_at: '2026-10-01', base: { ref: '6.7.x' } },
            { number: 3, merged_at: '2026-10-01', base: { ref: 'trunk' } },
        ],
    });

    assert.equal(await resolvePullRequestNumber({ github, context: pushContext() }), 3);
});

test('resolvePullRequestNumber returns null for a direct push', async () => {
    const { github } = fakeGitHub();

    assert.equal(await resolvePullRequestNumber({ github, context: pushContext() }), null);
});

test('collectAgentInput writes metadata, files without patches, and the diff', async () => {
    const dir = mkdtempSync(join(tmpdir(), 'docs-impact-'));
    const { github } = fakeGitHub();
    const { core } = fakeCore();

    await collectAgentInput({ github, context: pushContext({ pr_number: '7' }), core }, dir);

    const input = JSON.parse(readFileSync(join(dir, 'pr.json'), 'utf8'));
    assert.deepEqual(input.pr, {
        number: 7,
        title: 'Title',
        url: 'https://github.com/shopware/shopware/pull/7',
        author: 'alice',
        labels: ['domain/inventory'],
        merged_at: '2026-10-01T00:00:00Z',
        base: 'trunk',
        body: '',
    });
    assert.deepEqual(input.files, [{ filename: 'src/a.php', status: 'modified', additions: 1, deletions: 1 }]);
    assert.equal(readFileSync(join(dir, 'pr.diff'), 'utf8'), 'diff --git a/x b/x');
});

test('collectAgentInput writes a null pull request for a direct push', async () => {
    const dir = mkdtempSync(join(tmpdir(), 'docs-impact-'));
    const { github } = fakeGitHub();
    const { core } = fakeCore();

    await collectAgentInput({ github, context: pushContext(), core }, dir);

    assert.deepEqual(JSON.parse(readFileSync(join(dir, 'pr.json'), 'utf8')), { pr: null });
    assert.equal(existsSync(join(dir, 'pr.diff')), false);
});

test('collectAgentInput keeps the per-file patches when the API refuses the diff as too large', async () => {
    const dir = mkdtempSync(join(tmpdir(), 'docs-impact-'));
    const { github } = fakeGitHub({ diffStatus: 406 });
    const { core, messages } = fakeCore();

    await collectAgentInput({ github, context: pushContext({ pr_number: '7' }), core }, dir);

    assert.equal(existsSync(join(dir, 'pr.diff')), false);
    assert.deepEqual(JSON.parse(readFileSync(join(dir, 'pr.json'), 'utf8')).files, [
        { filename: 'src/a.php', status: 'modified', additions: 1, deletions: 1, patch: '@@ -1 +1 @@' },
    ]);
    assert.ok(messages.some((m) => m.startsWith('warning:')));
});

test('collectAgentInput rethrows other diff errors', async () => {
    const dir = mkdtempSync(join(tmpdir(), 'docs-impact-'));
    const { github } = fakeGitHub({ diffStatus: 500 });
    const { core } = fakeCore();

    await assert.rejects(collectAgentInput({ github, context: pushContext({ pr_number: '7' }), core }, dir));
});

test('postComment does nothing without a docs candidate', async () => {
    const { github, calls } = fakeGitHub();
    const { core } = fakeCore();

    await postComment({ github, context: pushContext(), core }, { items: [{ type: 'noop' }] });

    assert.equal(calls.length, 0);
});

test('postComment creates a comment on the resolved pull request', async () => {
    const { github, calls } = fakeGitHub();
    const { core } = fakeCore();

    await postComment(
        { github, context: pushContext({ pr_number: '9' }), core },
        { items: [{ type: 'docs_candidate', audiences: 'end-user', summary: 'New setting' }] },
    );

    const create = calls.find((c) => c.method === 'createComment');
    assert.equal(create?.params.issue_number, 9);
    assert.ok(String(create?.params.body).includes('New setting'));
    assert.equal(String(create?.params.body).includes('@shopware'), false);
});

test('postComment updates its earlier comment instead of adding another', async () => {
    const { github, calls } = fakeGitHub({ comments: [{ id: 5, body: 'other' }, { id: 6, body: `${COMMENT_MARKER}\nold` }] });
    const { core } = fakeCore();

    await postComment(
        { github, context: pushContext({ pr_number: '9' }), core },
        { items: [{ type: 'docs_candidate', audiences: 'developer', summary: 'New event' }] },
    );

    assert.equal(calls.find((c) => c.method === 'updateComment')?.params.comment_id, 6);
    assert.equal(calls.some((c) => c.method === 'createComment'), false);
});

test('postComment rejects malformed agent output', async () => {
    const { github } = fakeGitHub();
    const { core } = fakeCore();
    const context = pushContext({ pr_number: '9' });

    await assert.rejects(
        postComment({ github, context, core }, { items: [{ type: 'docs_candidate', audiences: 'developer', summary: ' ' }] }),
        /empty summary/,
    );
    await assert.rejects(
        postComment({ github, context, core }, { items: [{ type: 'docs_candidate', audiences: 'sales', summary: 'x' }] }),
        /Unknown audience/,
    );
    await assert.rejects(
        postComment({ github, context, core }, {
            items: [
                { type: 'docs_candidate', audiences: 'developer', summary: 'a' },
                { type: 'docs_candidate', audiences: 'developer', summary: 'b' },
            ],
        }),
        /at most one/,
    );
});

test('postComment fails when the commit has no merged pull request', async () => {
    const { github } = fakeGitHub();
    const { core } = fakeCore();

    await assert.rejects(
        postComment({ github, context: pushContext(), core }, { items: [{ type: 'docs_candidate', audiences: 'developer', summary: 'x' }] }),
        /no merged pull request/,
    );
});

test('isDryRun accepts the boolean and string forms of the dispatch input', () => {
    assert.equal(isDryRun(pushContext({ pr_number: '9', dry_run: true })), true);
    assert.equal(isDryRun(pushContext({ pr_number: '9', dry_run: 'true' })), true);
    assert.equal(isDryRun(pushContext({ pr_number: '9', dry_run: 'false' })), false);
    assert.equal(isDryRun(pushContext()), false);
});

test('postComment in a dry run writes the comment to the step summary only', async () => {
    const { github, calls } = fakeGitHub({ comments: [{ id: 6, body: `${COMMENT_MARKER}\nold` }] });
    const { core } = fakeCore();
    const written: string[] = [];
    const summary = { addRaw: (text: string) => ({ write: async () => written.push(text) }) };

    await postComment(
        { github, context: pushContext({ pr_number: '9', dry_run: true }), core: { ...core, summary } },
        { items: [{ type: 'docs_candidate', audiences: 'developer', summary: 'New event' }] },
    );

    assert.equal(calls.some((c) => ['createComment', 'updateComment'].includes(c.method)), false);
    assert.equal(written.length, 1);
    assert.ok(written[0].includes('Dry run: comment for #9'));
    assert.ok(written[0].includes('New event'));
});
