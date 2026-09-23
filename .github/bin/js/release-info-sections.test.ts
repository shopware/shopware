import assert from 'node:assert/strict';
import { test } from 'node:test';
import { MILESTONE_LABEL_PREFIX, SKIP_CHECK_LABEL as MILESTONE_SKIP_LABEL } from './milestone-label.ts';
import { addedLineNumbers, checkReleaseInfoSections, evaluateReleaseInfoSections, repeatedHeadings, sectionByLine, SKIP_CHECK_LABEL, STATUS_CONTEXT } from './release-info-sections.ts';

// The state of trunk's RELEASE_INFO-6.7.md after the 6.7.13.x branch-off: 6.7.14.0
// collects new entries, 6.7.13.0 has shipped.
const HEAD_CONTENT = [
    '# 6.7.14.0 (upcoming)', // 1
    '', //                      2
    '## Features', //           3
    '', //                      4
    '### New thing', //         5
    '', //                      6
    'New body.', //             7
    '', //                      8
    '# 6.7.13.0', //            9
    '', //                     10
    '## Core', //              11
    '', //                     12
    'Shipped entry.', //       13
].join('\n');

/** The patch for an entry added as lines 5-8, inside the upcoming section. */
const UPCOMING_PATCH = [
    '@@ -2,3 +2,7 @@',
    ' ',
    ' ## Features',
    ' ',
    '+### New thing',
    '+',
    '+New body.',
    '+',
    ' # 6.7.13.0',
].join('\n');

/** The patch for the same entry misfiled as lines 11-14, inside the shipped section. */
const SHIPPED_PATCH = [
    '@@ -8,4 +8,8 @@',
    ' ',
    ' # 6.7.13.0',
    ' ',
    '+### New thing',
    '+',
    '+New body.',
    '+',
    ' ## Core',
].join('\n');

const MISFILED_HEAD_CONTENT = [
    '# 6.7.14.0 (upcoming)',
    '',
    '## Features',
    '',
    '',
    '',
    '',
    '',
    '# 6.7.13.0',
    '',
    '### New thing',
    '',
    'New body.',
    '',
    '## Core',
    '',
    'Shipped entry.',
].join('\n');

/** The upcoming section after a merge opened `## Features` a second time. */
const DUPLICATE_CATEGORY_CONTENT = [
    '# 6.7.14.0 (upcoming)', //  1
    '', //                       2
    '## Features', //            3
    '', //                       4
    '### New thing', //          5
    '', //                       6
    'New body.', //              7
    '', //                       8
    '## Features', //            9  the repetition
    '', //                      10
    '### Second thing', //      11
    '', //                      12
    'Another body.', //         13
    '', //                      14
    '# 6.7.13.0', //            15
].join('\n');

/** Adds lines 9-14, i.e. the second `## Features` and its entry. */
const DUPLICATE_CATEGORY_PATCH = [
    '@@ -7,3 +7,9 @@',
    ' New body.',
    ' ',
    '+## Features',
    '+',
    '+### Second thing',
    '+',
    '+Another body.',
    '+',
    ' # 6.7.13.0',
].join('\n');

/** The same entry heading cherry-picked into the section twice. */
const DUPLICATE_ENTRY_CONTENT = [
    '# 6.7.14.0 (upcoming)', //  1
    '', //                       2
    '## Features', //            3
    '', //                       4
    '### New thing', //          5
    '', //                       6
    'New body.', //              7
    '', //                       8
    '### New thing', //          9  the repetition
    '', //                      10
    'Picked twice.', //         11
    '', //                      12
    '# 6.7.13.0', //            13
].join('\n');

const DUPLICATE_ENTRY_PATCH = [
    '@@ -7,3 +7,7 @@',
    ' New body.',
    ' ',
    '+### New thing',
    '+',
    '+Picked twice.',
    '+',
    ' # 6.7.13.0',
].join('\n');

/** `DUPLICATE_CATEGORY_CONTENT` with one body line added to the *first* block. */
const UNTOUCHED_DUPLICATE_CONTENT = [
    '# 6.7.14.0 (upcoming)', //  1
    '', //                       2
    '## Features', //            3
    '', //                       4
    '### New thing', //          5
    '', //                       6
    'New body.', //              7
    'More body.', //             8  the only added line
    '', //                       9
    '## Features', //           10  a repetition the PR did not write
    '', //                      11
    '### Second thing', //      12
    '', //                      13
    '# 6.7.13.0', //            14
].join('\n');

const UNTOUCHED_DUPLICATE_PATCH = [
    '@@ -5,3 +5,4 @@',
    ' ### New thing',
    ' ',
    ' New body.',
    '+More body.',
].join('\n');

const evaluate = (labels: string[], files: { filename: string; patch?: string; headContent?: string }[]) =>
    evaluateReleaseInfoSections({ labels, files });

test('addedLineNumbers walks hunks, removals, and context correctly', () => {
    const patch = [
        '@@ -1,3 +1,4 @@',
        ' context',
        '+added line 2',
        ' context',
        '-removed',
        '+added line 4',
        '@@ -10,2 +11,3 @@',
        ' context',
        '+added line 12',
        ' context',
        '\\ No newline at end of file',
    ].join('\n');

    assert.deepEqual(addedLineNumbers(patch), [2, 4, 12]);
});

test('sectionByLine assigns the heading line to its own section', () => {
    const sections = sectionByLine(HEAD_CONTENT);

    assert.equal(sections[0], '6.7.14.0'); // the `(upcoming)` suffix is ignored
    assert.equal(sections[7], '6.7.14.0'); // last line before the next heading
    assert.equal(sections[8], '6.7.13.0'); // the heading line itself
    assert.equal(sections[12], '6.7.13.0');
});

test('sectionByLine maps lines above the first version heading to null', () => {
    assert.deepEqual(sectionByLine('preamble\n# 6.7.14.0'), [null, '6.7.14.0']);
});

test('a version heading quoted inside a code fence does not open a section', () => {
    const sections = sectionByLine([
        '# 6.7.14.0 (upcoming)',
        '```',
        '# 6.7.13.0',
        '```',
        'still upcoming',
    ].join('\n'));

    assert.deepEqual(sections, ['6.7.14.0', '6.7.14.0', '6.7.14.0', '6.7.14.0', '6.7.14.0']);
});

test('an info-string line cannot close a fence, only a bare one can', () => {
    // Regression: RELEASE_INFO-6.7.md ships an unclosed ```javascript block whose
    // next marker is a ```js opener. Toggling on every marker flipped the fence
    // state for the rest of the file, so #19225's misfiled entry passed as green.
    const sections = sectionByLine([
        '# 6.7.14.0 (upcoming)', // 1
        '```javascript', //         2  opened and never closed by its author
        'code();', //               3
        '### heading in code', //   4
        '```js', //                 5  info string: content, not a closer
        'more();', //               6
        '```', //                   7  bare: closes the block from line 2
        '# 6.7.13.0', //            8
        'entry', //                 9
    ].join('\n'));

    assert.equal(sections[3], '6.7.14.0');
    assert.equal(sections[7], '6.7.13.0');
    assert.equal(sections[8], '6.7.13.0');
});

test('a closing fence must be at least as long as the opener and use the same character', () => {
    const sections = sectionByLine([
        '# 6.7.14.0 (upcoming)', // 1
        '````', //                  2
        '```', //                   3  shorter: content
        '~~~~', //                  4  wrong character: content
        '`````', //                 5  longer: closes
        '# 6.7.13.0', //            6
    ].join('\n'));

    assert.deepEqual(sections.slice(2, 6), ['6.7.14.0', '6.7.14.0', '6.7.14.0', '6.7.13.0']);
});

test('repeatedHeadings reports a category opened twice in the same section', () => {
    assert.deepEqual(repeatedHeadings(DUPLICATE_CATEGORY_CONTENT), [
        { section: '6.7.14.0', heading: '## Features', lines: [3, 9] },
    ]);
});

test('repeatedHeadings reports an entry heading documented twice in the same section', () => {
    assert.deepEqual(repeatedHeadings(DUPLICATE_ENTRY_CONTENT), [
        { section: '6.7.14.0', heading: '### New thing', lines: [5, 9] },
    ]);
});

test('the same category in two different version sections is not a repetition', () => {
    const content = [
        '# 6.7.14.0 (upcoming)',
        '## Features',
        '### New thing',
        '# 6.7.13.0',
        '## Features',
        '### Shipped thing',
    ].join('\n');

    assert.deepEqual(repeatedHeadings(content), []);
});

test('the heading level is part of the identity, so an entry may repeat its category name', () => {
    const content = ['# 6.7.14.0 (upcoming)', '## Features', '### Features'].join('\n');

    assert.deepEqual(repeatedHeadings(content), []);
});

test('a repeated heading is recognised across case and whitespace', () => {
    assert.deepEqual(repeatedHeadings(['# 6.7.14.0 (upcoming)', '## Features', '##   features'].join('\n')), [
        { section: '6.7.14.0', heading: '## Features', lines: [2, 3] },
    ]);
});

test('a heading quoted inside a code fence is not a repetition', () => {
    const content = [
        '# 6.7.14.0 (upcoming)',
        '## Features',
        '```markdown',
        '## Features',
        '```',
    ].join('\n');

    assert.deepEqual(repeatedHeadings(content), []);
});

test('three occurrences are reported as one finding with every line', () => {
    const content = ['# 6.7.14.0 (upcoming)', '## Core', '## Core', '## Core'].join('\n');

    assert.deepEqual(repeatedHeadings(content), [{ section: '6.7.14.0', heading: '## Core', lines: [2, 3, 4] }]);
});

test('a PR that does not touch a RELEASE_INFO file is fine', () => {
    const verdict = evaluate([`${MILESTONE_LABEL_PREFIX}6.7.14.0`], [{ filename: 'src/Core/Framework/Api/README.md', patch: '@@ -1 +1 @@\n+x', headContent: 'x' }]);

    assert.equal(verdict.status, 'ok');
});

test('entries added to the section the milestone names are fine', () => {
    const verdict = evaluate(
        [`${MILESTONE_LABEL_PREFIX}6.7.14.0`],
        [{ filename: 'RELEASE_INFO-6.7.md', patch: UPCOMING_PATCH, headContent: HEAD_CONTENT }],
    );

    assert.equal(verdict.status, 'ok');
});

test('entries added to an already-shipped section are invalid', () => {
    // Regression: this is #17997, which merged into trunk after the 6.7.13.x
    // branch-off and put its RELEASE_INFO entries under 6.7.13.0.
    const verdict = evaluate(
        [`${MILESTONE_LABEL_PREFIX}6.7.14.0`],
        [{ filename: 'RELEASE_INFO-6.7.md', patch: SHIPPED_PATCH, headContent: MISFILED_HEAD_CONTENT }],
    );

    assert.equal(verdict.status, 'invalid');
    assert.match(verdict.message, /`6\.7\.13\.0` section/);
    assert.match(verdict.message, /`milestone\/6\.7\.14\.0`/);
});

test('a backport may open and fill the patch section its milestone names', () => {
    const headContent = [
        '# 6.7.14.0 (upcoming)',
        '',
        '# 6.7.13.1',
        '',
        'Backported fix.',
        '',
        '# 6.7.13.0',
    ].join('\n');
    const patch = [
        '@@ -1,2 +1,6 @@',
        ' # 6.7.14.0 (upcoming)',
        ' ',
        '+# 6.7.13.1',
        '+',
        '+Backported fix.',
        '+',
        ' # 6.7.13.0',
    ].join('\n');

    const verdict = evaluate(
        [`${MILESTONE_LABEL_PREFIX}6.7.13.1`, 'backport-6.7.13.x'],
        [{ filename: 'RELEASE_INFO-6.7.md', patch, headContent }],
    );

    assert.equal(verdict.status, 'ok');
});

test('lines added above the first version heading are invalid', () => {
    const verdict = evaluate(
        [`${MILESTONE_LABEL_PREFIX}6.7.14.0`],
        [{ filename: 'RELEASE_INFO-6.7.md', patch: '@@ -1,2 +1,3 @@\n+preamble\n # 6.7.14.0 (upcoming)\n ', headContent: `preamble\n${HEAD_CONTENT}` }],
    );

    assert.equal(verdict.status, 'invalid');
    assert.match(verdict.message, /above the first version heading/);
});

test('pure removals cannot misfile an entry and pass', () => {
    const verdict = evaluate(
        [`${MILESTONE_LABEL_PREFIX}6.7.14.0`],
        [{ filename: 'RELEASE_INFO-6.7.md', patch: '@@ -11,3 +11,1 @@\n ## Core\n-\n-Shipped entry.', headContent: HEAD_CONTENT }],
    );

    assert.equal(verdict.status, 'ok');
});

test('a deleted RELEASE_INFO file has no head content and no additions, and passes', () => {
    const verdict = evaluate(
        [`${MILESTONE_LABEL_PREFIX}6.7.14.0`],
        [{ filename: 'RELEASE_INFO-6.7.md', patch: '@@ -1,2 +0,0 @@\n-# 6.7.14.0 (upcoming)\n-' }],
    );

    assert.equal(verdict.status, 'ok');
});

test('a diff too large for the API to return is invalid, not silently green', () => {
    const verdict = evaluate(
        [`${MILESTONE_LABEL_PREFIX}6.7.14.0`],
        [{ filename: 'RELEASE_INFO-6.7.md', headContent: HEAD_CONTENT }],
    );

    assert.equal(verdict.status, 'invalid');
    assert.match(verdict.message, /too large/);
});

test('without a single valid milestone label the verdict is left to the milestone check', () => {
    const files = [{ filename: 'RELEASE_INFO-6.7.md', patch: SHIPPED_PATCH, headContent: MISFILED_HEAD_CONTENT }];

    assert.equal(evaluate([], files).status, 'skipped');
    assert.equal(evaluate([`${MILESTONE_LABEL_PREFIX}6.7.13.0`, `${MILESTONE_LABEL_PREFIX}6.7.14.0`], files).status, 'skipped');
    assert.equal(evaluate([`${MILESTONE_LABEL_PREFIX}6.7.x`], files).status, 'skipped');
});

test(`${SKIP_CHECK_LABEL} opts out of the section check`, () => {
    const verdict = evaluate(
        [`${MILESTONE_LABEL_PREFIX}6.7.14.0`, SKIP_CHECK_LABEL],
        [{ filename: 'RELEASE_INFO-6.7.md', patch: SHIPPED_PATCH, headContent: MISFILED_HEAD_CONTENT }],
    );

    assert.equal(verdict.status, 'skipped');
});

test(`${MILESTONE_SKIP_LABEL} opts out too, because the milestone cannot be trusted then`, () => {
    const verdict = evaluate(
        [`${MILESTONE_LABEL_PREFIX}6.7.14.0`, MILESTONE_SKIP_LABEL],
        [{ filename: 'RELEASE_INFO-6.7.md', patch: SHIPPED_PATCH, headContent: MISFILED_HEAD_CONTENT }],
    );

    assert.equal(verdict.status, 'skipped');
});

test('opening a category heading the section already has is invalid', () => {
    // Regression: RELEASE_INFO-6.7.md's `# 6.7.6.0` carries two `## Administration`
    // blocks, which is what two merges opening the category independently produce.
    const verdict = evaluate(
        [`${MILESTONE_LABEL_PREFIX}6.7.14.0`],
        [{ filename: 'RELEASE_INFO-6.7.md', patch: DUPLICATE_CATEGORY_PATCH, headContent: DUPLICATE_CATEGORY_CONTENT }],
    );

    assert.equal(verdict.status, 'invalid');
    assert.match(verdict.message, /`## Features` appears 2× in the `6\.7\.14\.0` section of `RELEASE_INFO-6\.7\.md` \(lines 3, 9\)/);
    assert.match(verdict.short, /duplicate "## Features"/);
});

test('adding an entry heading the section already documents is invalid', () => {
    const verdict = evaluate(
        [`${MILESTONE_LABEL_PREFIX}6.7.14.0`],
        [{ filename: 'RELEASE_INFO-6.7.md', patch: DUPLICATE_ENTRY_PATCH, headContent: DUPLICATE_ENTRY_CONTENT }],
    );

    assert.equal(verdict.status, 'invalid');
    assert.match(verdict.message, /`### New thing` appears 2×/);
});

test('a repetition the PR did not write does not fail it', () => {
    const verdict = evaluate(
        [`${MILESTONE_LABEL_PREFIX}6.7.14.0`],
        [{ filename: 'RELEASE_INFO-6.7.md', patch: UNTOUCHED_DUPLICATE_PATCH, headContent: UNTOUCHED_DUPLICATE_CONTENT }],
    );

    assert.equal(verdict.status, 'ok');
});

test('a repetition is reported even without a milestone label, because it needs none', () => {
    const files = [{ filename: 'RELEASE_INFO-6.7.md', patch: DUPLICATE_CATEGORY_PATCH, headContent: DUPLICATE_CATEGORY_CONTENT }];

    assert.equal(evaluate([], files).status, 'invalid');
    assert.equal(evaluate([MILESTONE_SKIP_LABEL], files).status, 'invalid');
});

test('a misfiled entry outranks a repetition, because moving it can resolve both', () => {
    const verdict = evaluate(
        [`${MILESTONE_LABEL_PREFIX}6.7.13.0`],
        [{ filename: 'RELEASE_INFO-6.7.md', patch: DUPLICATE_CATEGORY_PATCH, headContent: DUPLICATE_CATEGORY_CONTENT }],
    );

    assert.equal(verdict.status, 'invalid');
    assert.match(verdict.message, /`6\.7\.14\.0` section/);
    assert.doesNotMatch(verdict.message, /appears 2×/);
});

test(`${SKIP_CHECK_LABEL} opts out of the repetition check too`, () => {
    const verdict = evaluate(
        [`${MILESTONE_LABEL_PREFIX}6.7.14.0`, SKIP_CHECK_LABEL],
        [{ filename: 'RELEASE_INFO-6.7.md', patch: DUPLICATE_CATEGORY_PATCH, headContent: DUPLICATE_CATEGORY_CONTENT }],
    );

    assert.equal(verdict.status, 'skipped');
});

test('every short form fits the commit status limit', () => {
    const verdicts = [
        evaluate([`${MILESTONE_LABEL_PREFIX}6.7.14.0`], []),
        evaluate([`${MILESTONE_LABEL_PREFIX}6.7.14.0`], [{ filename: 'RELEASE_INFO-6.7.md', patch: UPCOMING_PATCH, headContent: HEAD_CONTENT }]),
        evaluate([`${MILESTONE_LABEL_PREFIX}6.7.14.0`], [{ filename: 'RELEASE_INFO-6.7.md', patch: SHIPPED_PATCH, headContent: MISFILED_HEAD_CONTENT }]),
        evaluate([`${MILESTONE_LABEL_PREFIX}6.7.14.0`], [{ filename: 'RELEASE_INFO-6.7.md', headContent: HEAD_CONTENT }]),
        evaluate([], [{ filename: 'RELEASE_INFO-6.7.md', patch: UPCOMING_PATCH, headContent: HEAD_CONTENT }]),
        evaluate([SKIP_CHECK_LABEL], [{ filename: 'RELEASE_INFO-6.7.md', patch: UPCOMING_PATCH, headContent: HEAD_CONTENT }]),
        evaluate([`${MILESTONE_LABEL_PREFIX}6.7.14.0`], [{ filename: 'RELEASE_INFO-6.7.md', patch: DUPLICATE_CATEGORY_PATCH, headContent: DUPLICATE_CATEGORY_CONTENT }]),
        // A `###` title is far longer than a category name, and several of them can
        // repeat at once, so both shapes have to stay inside the limit.
        evaluate([`${MILESTONE_LABEL_PREFIX}6.7.14.0`], [{
            filename: 'RELEASE_INFO-6.7.md',
            patch: ['@@ -1,1 +1,4 @@', ' # 6.7.14.0 (upcoming)', `+### ${'x'.repeat(200)}`, `+### ${'y'.repeat(200)}`, '+'].join('\n'),
            headContent: [
                '# 6.7.14.0 (upcoming)',
                `### ${'x'.repeat(200)}`,
                `### ${'y'.repeat(200)}`,
                '',
                `### ${'x'.repeat(200)}`,
                `### ${'y'.repeat(200)}`,
            ].join('\n'),
        }]),
    ];

    for (const verdict of verdicts) {
        assert.ok(verdict.short.length <= 140, `short form too long (${verdict.short.length}): ${verdict.short}`);
    }
});

type FakePull = { labels: string[]; files: { filename: string; status?: string; patch?: string }[]; headFiles?: Record<string, string> };

/** Toolkit stub; `pulls` maps a PR number to its state, `mergeGroup` switches the path. */
function fakeToolkit(pulls: Record<number, FakePull>, options: { mergeGroup?: { commits: { sha: string; pulls: number[] }[] } } = {}) {
    const statuses: { state: string; description: string; sha: string }[] = [];
    const headShaOf = (number: number) => `head-${number}`;

    const toolkit = {
        github: {
            rest: {
                pulls: {
                    get: async ({ pull_number }: { pull_number: number }) => {
                        const pull = pulls[pull_number];
                        assert.ok(pull, `unexpected pulls.get for #${pull_number}`);

                        return { data: { number: pull_number, head: { sha: headShaOf(pull_number) }, labels: pull.labels.map((name) => ({ name })) } };
                    },
                    listFiles: async ({ pull_number, page }: { pull_number: number; page: number }) => ({
                        data: page === 1 ? pulls[pull_number].files.map((file) => ({ status: 'modified', ...file })) : [],
                    }),
                },
                repos: {
                    getContent: async ({ path, ref }: { path: string; ref: string }) => {
                        const number = Number(/^head-(\d+)$/.exec(ref)?.[1]);
                        const content = pulls[number]?.headFiles?.[path];
                        assert.ok(content !== undefined, `unexpected getContent for ${path} at ${ref}`);

                        return { data: content };
                    },
                    createCommitStatus: async ({ state, description, context: ctx, sha }: { state: string; description: string; context: string; sha: string }) => {
                        assert.equal(ctx, STATUS_CONTEXT);
                        assert.ok(description.length <= 140, `status description too long: ${description.length}`);
                        statuses.push({ state, description, sha });

                        return {};
                    },
                    compareCommitsWithBasehead: async () => ({ data: { commits: (options.mergeGroup?.commits ?? []).map(({ sha }) => ({ sha })) } }),
                    listPullRequestsAssociatedWithCommit: async ({ commit_sha }: { commit_sha: string }) => ({
                        data: (options.mergeGroup?.commits.find(({ sha }) => sha === commit_sha)?.pulls ?? []).map((number) => ({ number })),
                    }),
                },
            },
        },
        core: {
            info: () => {},
            warning: () => {},
            error: () => {},
            setFailed: () => { throw new Error('the job must stay green; the verdict belongs in the commit status'); },
            summary: { addRaw: () => ({ write: async () => {} }) },
        },
        context: options.mergeGroup
            ? {
                eventName: 'merge_group',
                repo: { owner: 'shopware', repo: 'shopware' },
                payload: {
                    merge_group: { head_ref: 'refs/heads/gh-readonly-queue/trunk/pr-1-0123456789abcdef0123456789abcdef01234567', base_sha: 'aaaa', head_sha: 'bbbb' },
                },
            }
            : {
                eventName: 'pull_request_target',
                repo: { owner: 'shopware', repo: 'shopware' },
                payload: {
                    pull_request: { number: 1, head: { sha: headShaOf(1) }, labels: pulls[1].labels.map((name) => ({ name })) },
                },
            },
    };

    return { toolkit, statuses };
}

test('the single-PR path posts a failure status for a misfiled entry', async () => {
    const { toolkit, statuses } = fakeToolkit({
        1: {
            labels: [`${MILESTONE_LABEL_PREFIX}6.7.14.0`],
            files: [{ filename: 'RELEASE_INFO-6.7.md', patch: SHIPPED_PATCH }],
            headFiles: { 'RELEASE_INFO-6.7.md': MISFILED_HEAD_CONTENT },
        },
    });

    await checkReleaseInfoSections(toolkit);

    assert.deepEqual(statuses.map(({ state, sha }) => ({ state, sha })), [{ state: 'failure', sha: 'head-1' }]);
});

test('the single-PR path posts a success status when nothing is misfiled', async () => {
    const { toolkit, statuses } = fakeToolkit({
        1: {
            labels: [`${MILESTONE_LABEL_PREFIX}6.7.14.0`],
            files: [{ filename: 'RELEASE_INFO-6.7.md', patch: UPCOMING_PATCH }],
            headFiles: { 'RELEASE_INFO-6.7.md': HEAD_CONTENT },
        },
    });

    await checkReleaseInfoSections(toolkit);

    assert.deepEqual(statuses.map(({ state }) => state), ['success']);
});

test('the single-PR path posts a failure status for a repeated heading', async () => {
    const { toolkit, statuses } = fakeToolkit({
        1: {
            labels: [`${MILESTONE_LABEL_PREFIX}6.7.14.0`],
            files: [{ filename: 'RELEASE_INFO-6.7.md', patch: DUPLICATE_CATEGORY_PATCH }],
            headFiles: { 'RELEASE_INFO-6.7.md': DUPLICATE_CATEGORY_CONTENT },
        },
    });

    await checkReleaseInfoSections(toolkit);

    assert.deepEqual(statuses.map(({ state, sha }) => ({ state, sha })), [{ state: 'failure', sha: 'head-1' }]);
    assert.match(statuses[0].description, /duplicate "## Features"/);
});

test('the merge group gate checks every PR in a batch and names the offender', async () => {
    const { toolkit, statuses } = fakeToolkit(
        {
            1: { labels: [`${MILESTONE_LABEL_PREFIX}6.7.14.0`], files: [] },
            2: {
                labels: [`${MILESTONE_LABEL_PREFIX}6.7.14.0`],
                files: [{ filename: 'RELEASE_INFO-6.7.md', patch: SHIPPED_PATCH }],
                headFiles: { 'RELEASE_INFO-6.7.md': MISFILED_HEAD_CONTENT },
            },
        },
        { mergeGroup: { commits: [{ sha: 'c2', pulls: [2] }] } },
    );

    await checkReleaseInfoSections(toolkit);

    assert.deepEqual(statuses.map(({ state, sha }) => ({ state, sha })), [{ state: 'failure', sha: 'bbbb' }]);
    assert.match(statuses[0].description, /#2/);
});
