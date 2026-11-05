import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    ADMIN_ROOT,
    bugUrls,
    checkRegressionTest,
    countRegressionComments,
    evaluate,
    isAdminSource,
    isAdminSpec,
    SKIP_LABEL,
} from './regression-test-gate.ts';

const BUG = 'https://github.com/shopware/shopware/issues/1';
const SOURCE = `${ADMIN_ROOT}app/component/sw-foo/index.ts`;
const SPEC = `${ADMIN_ROOT}app/component/sw-foo/sw-foo.spec.ts`;

const regression = (see: string) => `/**\n * Rows got lost.\n * @regression\n * @see ${see}\n */\nit('keeps rows', () => {});\n`;

test('classifies Administration specs and sources', () => {
    assert.equal(isAdminSpec(SPEC), true);
    assert.equal(isAdminSpec(`${ADMIN_ROOT}app/component/sw-foo/sw-foo.spec/paging.spec.js`), true);
    assert.equal(isAdminSource(SPEC), false);
    assert.equal(isAdminSource(SOURCE), true);
    assert.equal(isAdminSource(`${ADMIN_ROOT}app/component/sw-foo/sw-foo.html.twig`), true);
    assert.equal(isAdminSource(`${ADMIN_ROOT}app/component/sw-foo/sw-foo.scss`), false);
    assert.equal(isAdminSource('src/Core/Framework/Foo.php'), false);
});

test('bugUrls keeps issues of type Bug or with the bug label', () => {
    assert.deepEqual(bugUrls([
        { url: BUG, issueType: { name: 'Bug' }, labels: { nodes: [] } },
        { url: 'https://github.com/shopware/shopware/issues/2', issueType: null, labels: { nodes: [{ name: 'bug' }] } },
        { url: 'https://github.com/shopware/shopware/issues/3', issueType: { name: 'Feature' }, labels: { nodes: [] } },
    ]), [BUG, 'https://github.com/shopware/shopware/issues/2']);
});

test('countRegressionComments counts only regressions that link a closed bug', () => {
    assert.equal(countRegressionComments(regression(BUG), [BUG]), 1);
    assert.equal(countRegressionComments(regression(`${BUG}#issuecomment-5`), [BUG]), 1);
    assert.equal(countRegressionComments(regression('https://github.com/shopware/shopware/issues/9'), [BUG]), 0);
    assert.equal(countRegressionComments(`/**\n * @behavior\n * @see ${BUG}\n */`, [BUG]), 0);
    assert.equal(countRegressionComments(`/**\n * @regression\n * @see ${BUG}0\n */`, [BUG]), 0);
    assert.equal(countRegressionComments(`/**\n * @regression-candidate\n * @see ${BUG}\n */`, [BUG]), 0);
});

test('evaluate fails a bug fix without a regression test and passes one with it', () => {
    const fix = { bugs: [BUG], labels: [], files: [SOURCE, SPEC] };

    assert.equal(evaluate({ ...fix, added: 0 }).state, 'failure');
    assert.equal(evaluate({ ...fix, added: 1 }).state, 'success');
});

test('evaluate skips pull requests it does not concern, and the label skip stays visible', () => {
    assert.equal(evaluate({ bugs: [], labels: [], files: [SOURCE], added: 0 }).state, 'skipped');
    assert.equal(evaluate({ bugs: [BUG], labels: [], files: ['src/Core/Foo.php'], added: 0 }).state, 'skipped');

    const labelled = evaluate({ bugs: [BUG], labels: [SKIP_LABEL], files: [SOURCE], added: 0 });
    assert.equal(labelled.state, 'skipped');
    assert.match(labelled.message, /without a regression test for https:\/\/github.com\/shopware\/shopware\/issues\/1/);
});

function toolkit({ base, head, bugType = 'Bug', renamedFrom }: { base: string | null; head: string; bugType?: string; renamedFrom?: string }) {
    const result = { failed: '', summary: '' };
    const github = {
        graphql: async () => ({
            repository: { pullRequest: { closingIssuesReferences: { nodes: [{ url: BUG, issueType: { name: bugType }, labels: { nodes: [] } }] } } },
        }),
        paginate: async () => [{ filename: SOURCE }, { filename: SPEC, previous_filename: renamedFrom }],
        rest: {
            pulls: { listFiles: {} },
            repos: {
                getContent: async ({ ref, path }: { ref: string; path: string }) => {
                    const content = ref === 'base-sha' ? (path === (renamedFrom ?? SPEC) ? base : null) : head;
                    if (content === null) {
                        throw Object.assign(new Error('Not Found'), { status: 404 });
                    }

                    return { data: content };
                },
            },
        },
    };
    const core = {
        info: () => {},
        setFailed: (message: string) => {
            result.failed = message;
        },
        summary: { addRaw: (text: string) => ({ write: async () => { result.summary += text; } }) },
    };
    const context = {
        repo: { owner: 'shopware', repo: 'shopware' },
        payload: { pull_request: { number: 7, base: { sha: 'base-sha' }, head: { sha: 'head-sha' }, labels: [] } },
    };

    return { github, core, context, result };
}

test('checkRegressionTest passes when the fix adds a regression test to a new spec', async () => {
    const { result, ...toolkitArgs } = toolkit({ base: null, head: regression(BUG) });
    await checkRegressionTest(toolkitArgs);

    assert.equal(result.failed, '');
    assert.match(result.summary, /Adds 1 regression test/);
});

test('checkRegressionTest fails when the regression test only existed before', async () => {
    const { result, ...toolkitArgs } = toolkit({ base: regression(BUG), head: regression(BUG) });
    await checkRegressionTest(toolkitArgs);

    assert.match(result.failed, /adds no Jest test tagged `@regression`/);
});

test('checkRegressionTest ignores pull requests that close no bug', async () => {
    const { result, ...toolkitArgs } = toolkit({ base: null, head: '', bugType: 'Feature' });
    await checkRegressionTest(toolkitArgs);

    assert.equal(result.failed, '');
    assert.match(result.summary, /closes no bug/);
});

test('checkRegressionTest reads a renamed spec under its old path on the base commit', async () => {
    const { result, ...toolkitArgs } = toolkit({ base: regression(BUG), head: regression(BUG), renamedFrom: `${ADMIN_ROOT}app/component/sw-foo/old.spec.ts` });
    await checkRegressionTest(toolkitArgs);

    assert.match(result.failed, /adds no Jest test tagged `@regression`/);
});
