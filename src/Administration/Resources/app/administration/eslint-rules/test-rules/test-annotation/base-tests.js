/**
 * @sw-package framework
 */

const { execFileSync } = require('node:child_process');
const { existsSync, mkdirSync, readFileSync, renameSync, writeFileSync } = require('node:fs');
const { join, relative, sep } = require('node:path');
const { parseForESLint } = require('@typescript-eslint/parser');
const { collectTestCases } = require('./test-cases');

const KINDS = new Set([
    'regression',
    'behavior',
    'characterization',
]);
const SPEC = /\.spec\.[jt]s$/;

const memory = new Map();

function git(repoRoot, args, input, encoding = 'utf8') {
    const output = execFileSync('git', args, {
        cwd: repoRoot,
        encoding,
        input,
        maxBuffer: 1024 * 1024 * 512,
        stdio: ['pipe', 'pipe', 'ignore'],
    });

    return encoding ? output.trim() : output;
}

/**
 * Whether git finds the repository of `repoRoot`. It doesn't when a worktree links to its git dir by
 * an absolute path that the current environment, such as a container, mounts somewhere else.
 */
function hasRepository(repoRoot) {
    try {
        git(repoRoot, ['rev-parse', '--git-dir']);
        return true;
    } catch {
        return false;
    }
}

const resolved = new Map();

/**
 * The commit whose untagged tests may stay untagged: the merge base of HEAD and `TEST_ANNOTATION_BASE`,
 * which defaults to `origin/trunk`. Returns null when git or that commit is not available.
 *
 * @example
 * resolveBase('/repo'); // '5b2590b67035e6f5cc3a993d3af6d19bd62df441'
 */
function resolveBase(repoRoot) {
    // A CLI run lints every file within seconds, an editor keeps its process for hours and may switch branches.
    const cached = resolved.get(repoRoot);
    if (cached && Date.now() - cached.at < 10_000) {
        return cached.sha;
    }

    let sha = null;
    try {
        sha = git(repoRoot, ['merge-base', 'HEAD', process.env.TEST_ANNOTATION_BASE ?? 'origin/trunk']);
    } catch {
        sha = null;
    }

    resolved.set(repoRoot, { at: Date.now(), sha });
    return sha;
}

function untaggedIn(text, filePath) {
    const { ast } = parseForESLint(text, {
        comment: true,
        range: true,
        loc: true,
        sourceType: 'module',
        ecmaVersion: 'latest',
        filePath,
    });
    const counts = {};

    collectTestCases(ast, text, ast.comments)
        .tests.filter(({ doc }) => !(doc?.tags ?? []).some((tag) => KINDS.has(tag.name)))
        .forEach(({ identity }) => {
            counts[identity] = (counts[identity] ?? 0) + 1;
        });

    return counts;
}

function collect(repoRoot, adminRoot, sha) {
    const adminPath = relative(repoRoot, adminRoot).split(sep).join('/');
    const files = git(repoRoot, [
        'ls-tree',
        '-r',
        '--name-only',
        sha,
        '--',
        `${adminPath}/src`,
    ])
        .split('\n')
        .filter((file) => SPEC.test(file));
    const batch = git(repoRoot, ['cat-file', '--batch'], files.map((file) => `${sha}:${file}`).join('\n'), null);
    const byFile = {};
    let offset = 0;

    files.forEach((file) => {
        const header = batch.subarray(offset, batch.indexOf(10, offset)).toString();
        const size = Number(header.split(' ')[2]);
        const start = batch.indexOf(10, offset) + 1;
        const text = batch.subarray(start, start + size).toString('utf8');
        offset = start + size + 1;

        let counts = {};
        try {
            counts = untaggedIn(text, file);
        } catch {
            // A spec that doesn't parse at the base has no tests to keep.
        }
        if (Object.keys(counts).length > 0) {
            byFile[file.slice(adminPath.length + 1)] = counts;
        }
    });

    return byFile;
}

/**
 * The untagged tests at the merge base, by spec path relative to the Administration root.
 * Cached per base commit in memory and in `node_modules/.cache`.
 * Returns null when the base can't be resolved.
 */
function untaggedAtBase(repoRoot, adminRoot) {
    const sha = resolveBase(repoRoot);
    if (!sha) {
        return null;
    }

    if (memory.has(sha)) {
        return memory.get(sha);
    }

    const cacheDir = join(adminRoot, 'node_modules', '.cache', 'test-annotation');
    const cacheFile = join(cacheDir, `${sha}.json`);
    let byFile;

    if (existsSync(cacheFile)) {
        byFile = JSON.parse(readFileSync(cacheFile, 'utf8'));
    } else {
        byFile = collect(repoRoot, adminRoot, sha);
        mkdirSync(cacheDir, { recursive: true });
        // Parallel ESLint workers may write the same file, so each writes its own and renames it.
        const partial = `${cacheFile}.${process.pid}`;
        writeFileSync(partial, JSON.stringify(byFile));
        renameSync(partial, cacheFile);
    }

    const anywhere = new Set(Object.values(byFile).flatMap((counts) => Object.keys(counts)));
    const result = { sha, byFile, anywhere };
    memory.set(sha, result);

    return result;
}

module.exports = {
    hasRepository,
    resolveBase,
    untaggedAtBase,
};
