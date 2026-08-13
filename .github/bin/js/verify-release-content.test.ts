import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { after, before, test } from 'node:test';
import {
    checkReleaseContent,
    commitStatusDescription,
    consoleReport,
    extractHeadings,
    type GitReader,
    markdownSummary,
    NOTE_COMMIT_WITHOUT_TEXT,
    NOTE_DOCS_ONLY,
    NOTE_TEXT_WITHOUT_COMMIT,
    createProcessGitReader,
    resolveVersionPrefix,
    STATUS_CONTEXT,
    type Toolkit,
    verifyReleaseContent,
} from './verify-release-content.ts';

const VERSION = '6.7.11';
const FILE = 'RELEASE_INFO-6.7.md';
const TRUNK = 'origin/trunk';
const BRANCH = 'origin/6.7.11.x';

type FakeData = {
    files?: Record<string, string>;
    introducing?: Record<string, string>;
    existingRefs?: string[];
    resolvedRefs?: Record<string, string>;
    ancestors?: string[];
    changed?: Record<string, string[]>;
};

function createFakeGitReader(data: FakeData): GitReader {
    return {
        showFile: (ref, path) => data.files?.[`${ref}:${path}`] ?? '',
        refExists: (ref) => (data.existingRefs ?? []).includes(ref),
        resolveCommit: (ref) => data.resolvedRefs?.[ref] ?? '',
        findIntroducingCommit: (_ref, needle) => data.introducing?.[needle] ?? '',
        isAncestor: (commit) => (data.ancestors ?? []).includes(commit),
        changedFiles: (commit) => data.changed?.[commit] ?? [],
    };
}

function releaseInfo(...headings: string[]): string {
    return `# 6.7.11.0\n\n${headings.map((heading) => `${heading}\nsome description\n`).join('\n')}`;
}

function verify(git: GitReader) {
    return verifyReleaseContent(git, { versionPrefix: VERSION, trunkRef: TRUNK, branchRef: BRANCH, releaseInfoFile: FILE });
}

test('a heading with a reachable feature commit is confirmed', () => {
    const content = releaseInfo('### Feature A');
    const result = verify(createFakeGitReader({
        files: { [`${TRUNK}:${FILE}`]: content, [`${BRANCH}:${FILE}`]: content },
        introducing: { '### Feature A': 'aaaa1111' },
        ancestors: ['aaaa1111'],
        changed: { aaaa1111: [FILE, 'src/Feature.php'] },
    }));

    assert.equal(result.total, 1);
    assert.equal(result.confirmed, 1);
    assert.deepEqual(result.missing, []);
    assert.deepEqual(result.warnings, []);
});

test('a heading absent from the branch with an unreachable commit is missing', () => {
    const result = verify(createFakeGitReader({
        files: { [`${TRUNK}:${FILE}`]: releaseInfo('### Feature A'), [`${BRANCH}:${FILE}`]: releaseInfo() },
        introducing: { '### Feature A': 'aaaa1111' },
        changed: { aaaa1111: [FILE, 'src/Feature.php'] },
    }));

    assert.equal(result.confirmed, 0);
    assert.deepEqual(result.missing, [{ heading: '### Feature A', sha: 'aaaa1111' }]);
});

test('a heading present but with an unreachable commit is warned', () => {
    const content = releaseInfo('### Feature A');
    const result = verify(createFakeGitReader({
        files: { [`${TRUNK}:${FILE}`]: content, [`${BRANCH}:${FILE}`]: content },
        introducing: { '### Feature A': 'aaaa1111' },
        changed: { aaaa1111: [FILE, 'src/Feature.php'] },
    }));

    assert.deepEqual(result.missing, []);
    assert.deepEqual(result.warnings, [{ heading: '### Feature A', sha: 'aaaa1111', note: NOTE_TEXT_WITHOUT_COMMIT }]);
});

test('a reachable commit whose heading is missing from the branch is warned', () => {
    const result = verify(createFakeGitReader({
        files: { [`${TRUNK}:${FILE}`]: releaseInfo('### Feature A'), [`${BRANCH}:${FILE}`]: releaseInfo() },
        introducing: { '### Feature A': 'aaaa1111' },
        ancestors: ['aaaa1111'],
        changed: { aaaa1111: [FILE, 'src/Feature.php'] },
    }));

    assert.deepEqual(result.warnings, [{ heading: '### Feature A', sha: 'aaaa1111', note: NOTE_COMMIT_WITHOUT_TEXT }]);
});

test('a docs-only introducing commit is warned', () => {
    const content = releaseInfo('### Feature A');
    const result = verify(createFakeGitReader({
        files: { [`${TRUNK}:${FILE}`]: content, [`${BRANCH}:${FILE}`]: content },
        introducing: { '### Feature A': 'aaaa1111' },
        ancestors: ['aaaa1111'],
        changed: { aaaa1111: [FILE] },
    }));

    assert.equal(result.confirmed, 0);
    assert.deepEqual(result.warnings, [{ heading: '### Feature A', sha: 'aaaa1111', note: NOTE_DOCS_ONLY }]);
});

test('no headings on trunk yields nothing to verify', () => {
    const result = verify(createFakeGitReader({ files: { [`${TRUNK}:${FILE}`]: '# 6.7.11.0\n\nno feature headings here\n' } }));

    assert.equal(result.total, 0);
    assert.deepEqual(result.missing, []);
    assert.deepEqual(result.warnings, []);
});

test('entries are sorted by the introducing commit', () => {
    const content = releaseInfo('### Feature Z', '### Feature A');
    const result = verify(createFakeGitReader({
        files: { [`${TRUNK}:${FILE}`]: content, [`${BRANCH}:${FILE}`]: releaseInfo() },
        introducing: { '### Feature Z': 'zzzz9999', '### Feature A': 'aaaa1111' },
        changed: { zzzz9999: [FILE, 'src/Z.php'], aaaa1111: [FILE, 'src/A.php'] },
    }));

    assert.deepEqual(result.missing.map((entry) => entry.sha), ['aaaa1111', 'zzzz9999']);
});

test('extractHeadings collects third-level headings within the version section', () => {
    const content = '# 6.7.11.0\n\n### Feature A\nbody\n#### deeper\n## other\n### Feature B\n';

    assert.deepEqual(extractHeadings(content, VERSION), ['### Feature A', '### Feature B']);
});

test('extractHeadings stops at the next top-level section', () => {
    const content = '# 6.7.11.0\n### In section\n# 6.8.0.0\n### Other version\n';

    assert.deepEqual(extractHeadings(content, VERSION), ['### In section']);
});

test('extractHeadings spans multiple sections sharing the prefix', () => {
    const content = '# 6.7.11.0\n### First patch\n# 6.7.11.1\n### Second patch\n';

    assert.deepEqual(extractHeadings(content, VERSION), ['### First patch', '### Second patch']);
});

test('consoleReport shows only the OK line when everything is confirmed', () => {
    const report = consoleReport({ total: 3, confirmed: 3, missing: [], warnings: [] }, FILE);

    assert.match(report, /OK: 3 of 3 entries confirmed present\. 0 need manual verification/);
    assert.doesNotMatch(report, /WARN|MISSING/);
});

test('consoleReport renders warnings with a linked commit and keeps the OK line', () => {
    const report = consoleReport(
        { total: 2, confirmed: 1, missing: [], warnings: [{ heading: '### Feature A', sha: 'aaaa11112222', note: NOTE_DOCS_ONLY }] },
        FILE,
        'https://github.com/shopware/shopware/commit',
    );

    assert.match(report, /WARN: 1 of 2 entries need manual verification:/);
    assert.match(report, /\? ### Feature A \[aaaa1111 \(https:\/\/github\.com\/shopware\/shopware\/commit\/aaaa11112222\)]/);
    assert.match(report, /OK: 1 of 2 entries confirmed present\. 1 need manual verification/);
});

test('consoleReport shows the failure epilogue and no OK line when entries are missing', () => {
    const report = consoleReport({ total: 1, confirmed: 0, missing: [{ heading: '### Feature A', sha: '' }], warnings: [] }, FILE);

    assert.match(report, /MISSING: 1 of 1 entries documented on trunk are absent/);
    assert.match(report, /x ### Feature A \[unknown]/);
    assert.match(report, /These features were documented in RELEASE_INFO-6\.7\.md on trunk/);
    assert.doesNotMatch(report, /OK:/);
});

test('markdownSummary shows the success line for a clean run', () => {
    const markdown = markdownSummary({ total: 2, confirmed: 2, missing: [], warnings: [] }, { versionPrefix: VERSION, branchRef: BRANCH, releaseInfoFile: FILE });

    assert.match(markdown, /## Release content verification — `6\.7\.11\.\*`/);
    assert.match(markdown, /\*\*2\*\* confirmed · \*\*0\*\* warning\(s\) · \*\*0\*\* missing \(of 2\)/);
    assert.match(markdown, /✅ All 2 documented entries are present and traceable\./);
});

test('markdownSummary renders the missing table with a linked commit and escaped pipes', () => {
    const markdown = markdownSummary(
        { total: 1, confirmed: 0, missing: [{ heading: '### Feature | A', sha: 'aaaa11112222' }], warnings: [] },
        { versionPrefix: VERSION, branchRef: BRANCH, releaseInfoFile: FILE, commitUrlBase: 'https://github.com/shopware/shopware/commit' },
    );

    assert.match(markdown, /### ❌ Missing from this release branch/);
    assert.match(markdown, /\| Feature \\\| A \| \[`aaaa1111`]\(https:\/\/github\.com\/shopware\/shopware\/commit\/aaaa11112222\) \|/);
});

test('markdownSummary escapes backslashes in a heading before the pipe', () => {
    const markdown = markdownSummary(
        { total: 1, confirmed: 0, missing: [{ heading: '### Path C:\\ | D', sha: '' }], warnings: [] },
        { versionPrefix: VERSION, branchRef: BRANCH, releaseInfoFile: FILE },
    );

    // Backslash doubled, then the literal pipe escaped: "C:\ | D" → "C:\\ \| D".
    assert.match(markdown, /\| Path C:\\\\ \\\| D \| `unknown` \|/);
});

test('commitStatusDescription summarises the three outcomes', () => {
    assert.equal(
        commitStatusDescription({ total: 0, confirmed: 0, missing: [], warnings: [] }, VERSION, '6.7.11.x'),
        'no entries for 6.7.11.* on trunk — nothing to verify',
    );
    assert.equal(
        commitStatusDescription({ total: 3, confirmed: 1, missing: [{ heading: '### A', sha: 'a' }, { heading: '### B', sha: 'b' }], warnings: [] }, VERSION, '6.7.11.x'),
        '2 of 3 documented entries missing from 6.7.11.x',
    );
    assert.equal(
        commitStatusDescription({ total: 4, confirmed: 3, missing: [], warnings: [{ heading: '### A', sha: 'a', note: NOTE_DOCS_ONLY }] }, VERSION, '6.7.11.x'),
        '3 of 4 entries confirmed, 1 need manual verification',
    );
});

test('resolveVersionPrefix prefers the explicit input, else derives it from the branch ref', () => {
    assert.equal(resolveVersionPrefix('6.7.12', '6.7.11.x'), '6.7.12');
    assert.equal(resolveVersionPrefix('', '6.7.11.x'), '6.7.11');
    assert.equal(resolveVersionPrefix(undefined, '6.7.11.x'), '6.7.11');
    assert.throws(() => resolveVersionPrefix(undefined, 'trunk'), /cannot derive a version prefix/);
});

test('checkReleaseContent posts a success status and stays quiet on findings', async () => {
    const content = releaseInfo('### Feature A');
    const git = createFakeGitReader({
        files: { [`${TRUNK}:${FILE}`]: content, [`${BRANCH}:${FILE}`]: content },
        existingRefs: [TRUNK, BRANCH],
        resolvedRefs: { [BRANCH]: 'branchsha' },
        introducing: { '### Feature A': 'aaaa1111' },
        ancestors: ['aaaa1111'],
        changed: { aaaa1111: [FILE, 'src/Feature.php'] },
    });

    const statuses: { state: string; context: string; description: string; sha: string }[] = [];
    const toolkit = {
        github: { rest: { repos: { createCommitStatus: async (options) => void statuses.push(options) } } },
        core: { info: () => {}, summary: { addRaw: () => ({ write: async () => {} }) } },
        context: { repo: { owner: 'shopware', repo: 'shopware' }, sha: 'headsha' },
    } as Toolkit;

    await withEnv({ VERSION_PREFIX: VERSION }, () => checkReleaseContent(toolkit, git));

    assert.equal(statuses.length, 1);
    assert.equal(statuses[0].state, 'success');
    assert.equal(statuses[0].context, STATUS_CONTEXT);
    assert.equal(statuses[0].sha, 'branchsha');
    assert.equal(statuses[0].description, '1 of 1 entries confirmed, 0 need manual verification');
});

test('checkReleaseContent posts a failure status when an entry is missing', async () => {
    const git = createFakeGitReader({
        files: { [`${TRUNK}:${FILE}`]: releaseInfo('### Feature A'), [`${BRANCH}:${FILE}`]: releaseInfo() },
        existingRefs: [TRUNK, BRANCH],
        resolvedRefs: { [BRANCH]: 'branchsha' },
        introducing: { '### Feature A': 'aaaa1111' },
        changed: { aaaa1111: [FILE, 'src/Feature.php'] },
    });

    const statuses: { state: string; description: string; sha: string }[] = [];
    const toolkit = {
        github: { rest: { repos: { createCommitStatus: async (options) => void statuses.push(options) } } },
        core: { info: () => {}, summary: { addRaw: () => ({ write: async () => {} }) } },
        context: { repo: { owner: 'shopware', repo: 'shopware' }, sha: 'headsha' },
    } as Toolkit;

    await withEnv({ VERSION_PREFIX: VERSION }, () => checkReleaseContent(toolkit, git));

    assert.equal(statuses[0].state, 'failure');
    assert.equal(statuses[0].sha, 'branchsha');
    assert.equal(statuses[0].description, '1 of 1 documented entries missing from 6.7.11.x');
});

test('checkReleaseContent throws when a required ref is not fetched', async () => {
    const git = createFakeGitReader({ existingRefs: [] });
    const toolkit = {
        github: { rest: { repos: { createCommitStatus: async () => {} } } },
        core: { info: () => {}, summary: { addRaw: () => ({ write: async () => {} }) } },
        context: { repo: { owner: 'shopware', repo: 'shopware' }, sha: 'headsha' },
    } as Toolkit;

    await withEnv({ VERSION_PREFIX: VERSION }, async () => {
        await assert.rejects(() => checkReleaseContent(toolkit, git), /origin\/trunk not found/);
    });
});

test('checkReleaseContent throws when the release branch cannot be resolved', async () => {
    const git = createFakeGitReader({ existingRefs: [TRUNK, BRANCH] });
    const toolkit = {
        github: { rest: { repos: { createCommitStatus: async () => {} } } },
        core: { info: () => {}, summary: { addRaw: () => ({ write: async () => {} }) } },
        context: { repo: { owner: 'shopware', repo: 'shopware' }, sha: 'headsha' },
    } as Toolkit;

    await withEnv({ VERSION_PREFIX: VERSION }, async () => {
        await assert.rejects(() => checkReleaseContent(toolkit, git), /could not be resolved to a commit/);
    });
});

async function withEnv(values: Record<string, string>, run: () => Promise<unknown> | unknown): Promise<void> {
    const previous = new Map(Object.keys(values).map((key) => [key, process.env[key]]));
    Object.assign(process.env, values);
    try {
        await run();
    } finally {
        for (const [key, value] of previous) {
            if (value === undefined) {
                delete process.env[key];
            } else {
                process.env[key] = value;
            }
        }
    }
}

// The git adapter shells out, so it is exercised against a real throwaway repository.
let repository: string;

const runGit = (args: string[]): string => execFileSync('git', args, { cwd: repository, encoding: 'utf8' });

function commit(message: string): string {
    runGit(['add', '-A']);
    runGit(['-c', 'user.email=test@example.com', '-c', 'user.name=Test', '-c', 'commit.gpgsign=false', 'commit', '-m', message]);

    return runGit(['rev-parse', 'HEAD']).trim();
}

before(() => {
    repository = mkdtempSync(join(tmpdir(), 'sw-git-reader-'));
    runGit(['-c', 'init.defaultBranch=main', 'init']);

    // Root commit, so the commits under test have a parent and `git diff-tree` reports their changes.
    writeFileSync(join(repository, 'README.md'), 'init\n');
    commit('init');
});

after(() => {
    rmSync(repository, { recursive: true, force: true });
});

test('createProcessGitReader reads refs, files, commits and changes from a real repository', () => {
    writeFileSync(join(repository, FILE), '# 6.7.11.0\n\n### Feature A\n');
    const firstCommit = commit('add release info');

    mkdirSync(join(repository, 'src'), { recursive: true });
    writeFileSync(join(repository, FILE), '# 6.7.11.0\n\n### Feature A\n### Feature B\n');
    writeFileSync(join(repository, 'src', 'Feature.php'), '<?php\n');
    const secondCommit = commit('add feature B and code');

    // A release branch that stops at the first commit, so the second commit is not reachable from it.
    runGit(['branch', 'release', firstCommit]);

    const reader = createProcessGitReader(repository);

    assert.equal(reader.refExists('main'), true);
    assert.equal(reader.refExists('does-not-exist'), false);
    assert.equal(reader.resolveCommit('release'), firstCommit);

    assert.match(reader.showFile('main', FILE), /### Feature B/);
    assert.doesNotMatch(reader.showFile('release', FILE), /### Feature B/);
    assert.equal(reader.showFile('main', 'does/not/exist.md'), '');

    assert.equal(reader.findIntroducingCommit('main', '### Feature B', FILE), secondCommit);
    assert.equal(reader.findIntroducingCommit('main', '### Never written', FILE), '');

    assert.equal(reader.isAncestor(firstCommit, 'main'), true);
    assert.equal(reader.isAncestor(secondCommit, 'release'), false);

    assert.deepEqual(reader.changedFiles(firstCommit), [FILE]);
    assert.deepEqual(reader.changedFiles(secondCommit), [FILE, 'src/Feature.php']);
});
