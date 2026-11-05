import assert from 'node:assert/strict';
import { mkdirSync, mkdtempSync, readFileSync, existsSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';
import {
    COMMENT_MARKER,
    buildComment,
    buildEndUserDocs,
    collectAgentInput,
    dispatchDocsChecks,
    latestEndUserPages,
    isDryRun,
    neutraliseMentions,
    parseAudiences,
    postComment,
    mergedPullRequests,
    redactSecrets,
    shouldCheck,
    unlinkDeadDocsLinks,
    warnIfEndUserDocsMissing,
} from './docs-impact.ts';

type Call = { method: string; params: Record<string, unknown> };

function fakeGitHub(options: {
    commits?: Array<{ sha: string; message: string }>;
    associated?: Record<string, Array<{ number: number; merged_at: string | null }>>;
    comments?: Array<{ id: number; body?: string; user?: { login: string } }>;
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
                compareCommitsWithBasehead: async (params: Record<string, unknown>) => {
                    calls.push({ method: 'compareCommitsWithBasehead', params });
                    const commits = (options.commits ?? []).map(({ sha, message }) => ({ sha, commit: { message } }));
                    const page = Number(params.page);
                    return { data: { commits: commits.slice((page - 1) * 100, page * 100) } };
                },
                listPullRequestsAssociatedWithCommit: async (params: Record<string, unknown>) => {
                    calls.push({ method: 'listPullRequestsAssociatedWithCommit', params });
                    return { data: options.associated?.[String(params.commit_sha)] ?? [] };
                },
            },
            actions: {
                createWorkflowDispatch: record('createWorkflowDispatch'),
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
                addLabels: record('addLabels'),
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
    payload: inputs ? { inputs } : { before: 'aaa111', after: 'abc123', head_commit: { id: 'abc123', message: 'fix: last (#3)' } },
});

const BOT = { login: 'github-actions[bot]' };

test('shouldCheck skips test, ci, chore, docs and refactor merges', () => {
    for (const message of [
        'test: cover the cart (#1)',
        'ci(acceptance): shard by suite (#2)',
        'chore: bump deps (#3)',
        'docs(admin): fix typo (#4)',
        'refactor(core): extract helper (#5)\n\nbody',
    ]) {
        assert.equal(shouldCheck(message), false, message);
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
        assert.equal(shouldCheck(message), true, message);
    }
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

test('mergedPullRequests checks every commit of the push, not only the last', async () => {
    const { github, calls } = fakeGitHub({
        commits: [
            { sha: 'c1', message: 'feat(admin): unit picker (#21)' },
            { sha: 'c2', message: 'fix: price (#22)\n\nbody' },
            { sha: 'c3', message: 'chore: bump deps (#23)' },
        ],
        associated: {
            c1: [{ number: 21, merged_at: '2026-11-04' }, { number: 99, merged_at: null }],
            c2: [{ number: 22, merged_at: '2026-11-04' }, { number: 21, merged_at: '2026-11-04' }],
            c3: [{ number: 23, merged_at: '2026-11-04' }],
        },
    });
    const { core, messages } = fakeCore();

    assert.deepEqual(await mergedPullRequests({ github, context: pushContext(), core }), [21, 22]);
    assert.equal(calls.find((c) => c.method === 'compareCommitsWithBasehead')?.params.basehead, 'aaa111...abc123');
    assert.equal(calls.some((c) => c.method === 'listPullRequestsAssociatedWithCommit' && c.params.commit_sha === 'c3'), false);
    assert.ok(messages.some((m) => m.startsWith('Skipping c3')));
});

test('mergedPullRequests pages through a large push', async () => {
    const commits = Array.from({ length: 150 }, (_, i) => ({ sha: `c${i}`, message: 'chore: x' }));
    const { github, calls } = fakeGitHub({ commits });
    const { core } = fakeCore();

    await mergedPullRequests({ github, context: pushContext(), core });

    assert.deepEqual(calls.filter((c) => c.method === 'compareCommitsWithBasehead').map((c) => c.params.page), [1, 2]);
});

test('mergedPullRequests falls back to the head commit without a usable before', async () => {
    const { github, calls } = fakeGitHub({ associated: { abc123: [{ number: 3, merged_at: '2026-11-04' }] } });
    const { core } = fakeCore();
    const context = pushContext();
    context.payload = { ...context.payload, before: '0000000000000000000000000000000000000000' };

    assert.deepEqual(await mergedPullRequests({ github, context, core }), [3]);
    assert.equal(calls.some((c) => c.method === 'compareCommitsWithBasehead'), false);
});

test('dispatchDocsChecks starts one dry run per pull request until posting is enabled', async () => {
    const commits = [
        { sha: 'c1', message: 'feat: a (#1)' },
        { sha: 'c2', message: 'fix: b (#2)' },
    ];
    const associated = { c1: [{ number: 1, merged_at: 'x' }], c2: [{ number: 2, merged_at: 'x' }] };

    for (const [env, dryRun] of [[{}, 'true'], [{ SW_DOCS_IMPACT_POST: 'true' }, 'false']] as const) {
        const { github, calls } = fakeGitHub({ commits, associated });
        const { core } = fakeCore();

        await dispatchDocsChecks({ github, context: pushContext(), core }, env);

        assert.deepEqual(
            calls.filter((c) => c.method === 'createWorkflowDispatch').map((c) => c.params),
            [1, 2].map((n) => ({ owner: 'shopware', repo: 'shopware', workflow_id: 'sw-docs-impact.lock.yml', ref: 'trunk', inputs: { pr_number: String(n), dry_run: dryRun } })),
        );
    }
});

test('dispatchDocsChecks caps the number of runs per push and warns about the rest', async () => {
    const commits = Array.from({ length: 22 }, (_, i) => ({ sha: `c${i}`, message: `feat: ${i}` }));
    const associated = Object.fromEntries(commits.map((c, i) => [c.sha, [{ number: i + 1, merged_at: 'x' }]]));
    const { github, calls } = fakeGitHub({ commits, associated });
    const { core, messages } = fakeCore();

    await dispatchDocsChecks({ github, context: pushContext(), core }, {});

    assert.equal(calls.filter((c) => c.method === 'createWorkflowDispatch').length, 20);
    assert.ok(messages.includes('warning: Not checking #21, #22: at most 20 pull requests per push'));
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

test('collectAgentInput rejects a missing or invalid pr_number', async () => {
    const dir = mkdtempSync(join(tmpdir(), 'docs-impact-'));
    const { github, calls } = fakeGitHub();
    const { core } = fakeCore();

    await assert.rejects(collectAgentInput({ github, context: pushContext({}), core }, dir), /Invalid pr_number/);
    await assert.rejects(collectAgentInput({ github, context: pushContext({ pr_number: '4x' }), core }, dir), /Invalid pr_number/);
    assert.equal(calls.length, 0);
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

    const label = calls.find((c) => c.method === 'addLabels');
    assert.equal(label?.params.issue_number, 9);
    assert.deepEqual(label?.params.labels, ['docs-candidate/end-user']);
});

test('postComment labels every audience of a mixed candidate', async () => {
    const { github, calls } = fakeGitHub();
    const { core } = fakeCore();

    await postComment(
        { github, context: pushContext({ pr_number: '9' }), core },
        { items: [{ type: 'docs_candidate', audiences: 'developer,end-user', summary: 'Both' }] },
    );

    assert.deepEqual(calls.find((c) => c.method === 'addLabels')?.params.labels, ['docs-candidate/developer', 'docs-candidate/end-user']);
});

test('postComment updates its earlier comment instead of adding another', async () => {
    const { github, calls } = fakeGitHub({ comments: [{ id: 5, body: 'other', user: BOT }, { id: 6, body: `${COMMENT_MARKER}\nold`, user: BOT }] });
    const { core } = fakeCore();

    await postComment(
        { github, context: pushContext({ pr_number: '9' }), core },
        { items: [{ type: 'docs_candidate', audiences: 'developer', summary: 'New event' }] },
    );

    assert.equal(calls.find((c) => c.method === 'updateComment')?.params.comment_id, 6);
    assert.equal(calls.some((c) => c.method === 'createComment'), false);
    assert.deepEqual(calls.find((c) => c.method === 'addLabels')?.params.labels, ['docs-candidate/developer']);
});

test('postComment never takes over a user comment that starts with the marker', async () => {
    const { github, calls } = fakeGitHub({ comments: [{ id: 6, body: `${COMMENT_MARKER}\ncopied`, user: { login: 'mallory' } }] });
    const { core } = fakeCore();

    await postComment(
        { github, context: pushContext({ pr_number: '9' }), core },
        { items: [{ type: 'docs_candidate', audiences: 'developer', summary: 'New event' }] },
    );

    assert.equal(calls.some((c) => c.method === 'updateComment'), false);
    assert.equal(calls.find((c) => c.method === 'createComment')?.params.issue_number, 9);
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

test('postComment fails without a pr_number input', async () => {
    const { github } = fakeGitHub();
    const { core } = fakeCore();

    await assert.rejects(
        postComment({ github, context: pushContext({}), core }, { items: [{ type: 'docs_candidate', audiences: 'developer', summary: 'x' }] }),
        /Invalid pr_number/,
    );
});

test('isDryRun accepts the boolean and string forms of the dispatch input', () => {
    assert.equal(isDryRun(pushContext({ pr_number: '9', dry_run: true })), true);
    assert.equal(isDryRun(pushContext({ pr_number: '9', dry_run: 'true' })), true);
    assert.equal(isDryRun(pushContext({ pr_number: '9', dry_run: 'false' })), false);
    assert.equal(isDryRun(pushContext({ pr_number: '9' })), false);
});

test('postComment in a dry run writes the comment to the step summary only', async () => {
    const { github, calls } = fakeGitHub({ comments: [{ id: 6, body: `${COMMENT_MARKER}\nold`, user: BOT }] });
    const { core } = fakeCore();
    const written: string[] = [];
    const summary = { addRaw: (text: string) => ({ write: async () => written.push(text) }) };

    await postComment(
        { github, context: pushContext({ pr_number: '9', dry_run: true }), core: { ...core, summary } },
        { items: [{ type: 'docs_candidate', audiences: 'developer', summary: 'New event' }] },
    );

    assert.equal(calls.some((c) => ['createComment', 'updateComment', 'addLabels'].includes(c.method)), false);
    assert.equal(written.length, 1);
    assert.ok(written[0].includes('Dry run: comment for #9'));
    assert.ok(written[0].includes('New event'));
});

test('unlinkDeadDocsLinks turns dead docs links into plain text and checks each URL once', async () => {
    const checked: string[] = [];
    const isDead = async (url: string) => {
        checked.push(url);
        return url.endsWith('/gone');
    };

    const summary = [
        '- **Adjust** [Products](https://docs.shopware.com/en/gone): a',
        '- **Adjust** [Products again](https://docs.shopware.com/en/gone): b',
        '- **Add** [Rate limiter](https://developer.shopware.com/docs/rate-limiter.html): c',
        '- See [the PR](https://github.com/shopware/shopware/pull/1)',
    ].join('\n');

    assert.equal(
        await unlinkDeadDocsLinks(summary, isDead),
        [
            '- **Adjust** Products: a',
            '- **Adjust** Products again: b',
            '- **Add** [Rate limiter](https://developer.shopware.com/docs/rate-limiter.html): c',
            '- See [the PR](https://github.com/shopware/shopware/pull/1)',
        ].join('\n'),
    );
    assert.deepEqual(checked, ['https://docs.shopware.com/en/gone', 'https://developer.shopware.com/docs/rate-limiter.html']);
});

test('postComment posts dead docs links as plain text', async () => {
    const { github, calls } = fakeGitHub();
    const { core } = fakeCore();

    await postComment(
        { github, context: pushContext({ pr_number: '9' }), core },
        { items: [{ type: 'docs_candidate', audiences: 'end-user', summary: '- **Adjust** [Products](https://docs.shopware.com/en/gone): a' }] },
        async () => true,
    );

    const body = String(calls.find((c) => c.method === 'createComment')?.params.body);
    assert.ok(body.includes('- **Adjust** Products: a'));
    assert.doesNotMatch(body, /\[Products\]\(/);
});

test('redactSecrets removes the whole header string and each header value', () => {
    const raw = 'Authorization=Bearer abcdef123456,x-api-key=k3y-0987654321,x=short';

    assert.equal(
        redactSecrets(`all: ${raw} / token: abcdef123456 / key: k3y-0987654321 / short`, raw),
        'all: [redacted] / token: [redacted] / key: [redacted] / short',
    );
    assert.equal(redactSecrets('nothing to hide', undefined), 'nothing to hide');
    assert.equal(redactSecrets('nothing to hide', ''), 'nothing to hide');
});

test('postComment redacts the configured secret before posting', async () => {
    const { github, calls } = fakeGitHub();
    const { core } = fakeCore();
    process.env.SW_DOCS_IMPACT_REDACT = 'Authorization=Bearer abcdef123456';

    try {
        await postComment(
            { github, context: pushContext({ pr_number: '9' }), core },
            { items: [{ type: 'docs_candidate', audiences: 'developer', summary: 'leak abcdef123456' }] },
        );
    } finally {
        delete process.env.SW_DOCS_IMPACT_REDACT;
    }

    const body = String(calls.find((c) => c.method === 'createComment')?.params.body);
    assert.ok(body.includes('leak [redacted]'));
    assert.equal(body.includes('abcdef123456'), false);
});

test('latestEndUserPages keeps the highest article version per page and maps it to its URL', () => {
    assert.deepEqual(
        latestEndUserPages([
            'shopware-6/settings/countries/v1-6-0-0.md',
            'shopware-6/settings/countries/v1-10-0-0.md',
            'shopware-6/settings/countries/v1-5-0-1.md',
            'shopware-6/settings/index.md',
            'shopware-account/settings/user/v1-0-0.md',
            'shopware-6/migration-en/magento/v1-0-0-0.md',
            'shopware-6/settings/countries/notes.md',
            'other/page/v1-0-0-0.md',
        ]),
        [
            { source: 'shopware-6/migration-en/magento/v1-0-0-0.md', target: 'shopware-6/migration-en/magento.md', url: 'https://docs.shopware.com/en/migration-en/magento' },
            { source: 'shopware-6/settings/index.md', target: 'shopware-6/settings.md', url: 'https://docs.shopware.com/en/shopware-6-en/settings' },
            { source: 'shopware-6/settings/countries/v1-10-0-0.md', target: 'shopware-6/settings/countries.md', url: 'https://docs.shopware.com/en/shopware-6-en/settings/countries' },
            { source: 'shopware-account/settings/user/v1-0-0.md', target: 'shopware-account/settings/user.md', url: 'https://docs.shopware.com/en/account-en/settings/user' },
        ],
    );
});

test('buildEndUserDocs writes one file per page with its URL on the first line', () => {
    const content = mkdtempSync(join(tmpdir(), 'enduser-src-'));
    const out = mkdtempSync(join(tmpdir(), 'enduser-out-'));
    for (let i = 0; i < 100; i++) {
        mkdirSync(join(content, 'shopware-6', `page-${i}`), { recursive: true });
        writeFileSync(join(content, 'shopware-6', `page-${i}`, 'v1-0-0-0.md'), `old ${i}`);
        writeFileSync(join(content, 'shopware-6', `page-${i}`, 'v1-1-0-0.md'), `new ${i}`);
    }

    assert.equal(buildEndUserDocs(content, out), 100);
    assert.equal(readFileSync(join(out, 'shopware-6', 'page-7.md'), 'utf8'), 'URL: https://docs.shopware.com/en/shopware-6-en/page-7\n\nnew 7');
});

test('buildEndUserDocs refuses a suspiciously small content tree', () => {
    const content = mkdtempSync(join(tmpdir(), 'enduser-src-'));
    mkdirSync(join(content, 'shopware-6', 'only'), { recursive: true });
    writeFileSync(join(content, 'shopware-6', 'only', 'v1-0-0-0.md'), 'x');

    assert.throws(() => buildEndUserDocs(content, mkdtempSync(join(tmpdir(), 'enduser-out-'))), /Only 1 end-user pages/);
});

test('warnIfEndUserDocsMissing warns in the log and the run summary only when the index is missing', async () => {
    const dir = mkdtempSync(join(tmpdir(), 'docs-impact-'));
    for (const [path, expected] of [[dir, 0], [join(dir, 'enduser-docs'), 1]] as const) {
        const { core, messages } = fakeCore();
        const written: string[] = [];
        const summary = { addRaw: (text: string) => ({ write: async () => written.push(text) }) };

        await warnIfEndUserDocsMissing({ ...core, summary }, path);

        assert.equal(messages.filter((m) => m.startsWith('warning: End-user documentation is unavailable')).length, expected);
        assert.equal(written.length, expected);
    }
});
