import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { afterEach, beforeEach, test } from 'node:test';
import { eslintTotals, knipTotals } from './check-baseline-growth.ts';

const SCRIPT = join(dirname(fileURLToPath(import.meta.url)), 'check-baseline-growth.ts');
const ESLINT = 'admin/eslint-suppressions.json';
const KNIP = 'admin/knip-baseline.json';
const CONFIG = 'admin/eslint.config.ts';

let repo: string;
let baseSha: string;

const git = (...args: string[]): string => execFileSync('git', args, { cwd: repo, encoding: 'utf8' }).trim();

const write = (path: string, content: unknown): void => {
    mkdirSync(join(repo, dirname(path)), { recursive: true });
    writeFileSync(join(repo, path), JSON.stringify(content));
};

const touch = (path: string): void => {
    mkdirSync(join(repo, dirname(path)), { recursive: true });
    writeFileSync(join(repo, path), '');
};

const run = (...args: string[]) => {
    const result = spawnSync(process.execPath, [SCRIPT, ...args], { cwd: repo, encoding: 'utf8' });

    return { status: result.status, output: `${result.stdout}${result.stderr}` };
};

beforeEach(() => {
    repo = mkdtempSync(join(tmpdir(), 'baseline-growth-'));
    git('init', '--quiet');
    git('config', 'user.email', 'test@example.com');
    git('config', 'user.name', 'Test');

    write(ESLINT, {
        'src/a.js': { 'sw-core-rules/no-javascript-files': { count: 1 }, complexity: { count: 2 } },
        'src/b.js': { 'sw-core-rules/no-javascript-files': { count: 1 } },
    });
    write(KNIP, ['exports|src/a.ts|unused', 'types|src/b.ts|Unused']);
    touch('admin/src/a.js');
    touch('admin/src/b.js');
    writeFileSync(join(repo, CONFIG), 'export default [];');
    git('add', '.');
    git('commit', '--quiet', '-m', 'base');
    baseSha = git('rev-parse', 'HEAD');
});

afterEach(() => {
    rmSync(repo, { recursive: true, force: true });
});

test('eslintTotals sums the counts per rule over all files', () => {
    assert.deepEqual(
        eslintTotals({
            'a.js': { complexity: { count: 2 }, 'max-params': { count: 1 } },
            'b.js': { complexity: { count: 3 } },
        }),
        { complexity: 5, 'max-params': 1 },
    );
});

test('knipTotals counts the entries per issue type', () => {
    assert.deepEqual(knipTotals(['exports|a.ts|x', 'exports|b.ts|y', 'files|c.ts|c.ts']), { exports: 2, files: 1 });
});

test('passes and prints the totals when nothing changed', () => {
    const result = run('--base', baseSha, ESLINT, KNIP);

    assert.equal(result.status, 0);
    assert.match(result.output, /complexity: 2 -> 2/);
    assert.match(result.output, /exports: 1 -> 1/);
    assert.match(result.output, /Compared 2 baseline\(s\)/);
});

test('passes when a baseline shrinks', () => {
    write(ESLINT, { 'src/a.js': { 'sw-core-rules/no-javascript-files': { count: 1 } } });

    const result = run('--base', baseSha, ESLINT);

    assert.equal(result.status, 0);
    assert.match(result.output, /complexity: 2 -> 0/);
});

test('fails when a rule total grows', () => {
    write(ESLINT, {
        'src/a.js': { 'sw-core-rules/no-javascript-files': { count: 1 }, complexity: { count: 3 } },
        'src/b.js': { 'sw-core-rules/no-javascript-files': { count: 1 } },
    });

    const result = run('--base', baseSha, ESLINT);

    assert.equal(result.status, 1);
    assert.match(result.output, /complexity: 2 -> 3 {2}<-- grew/);
});

test('fails when a knip issue type grows', () => {
    write(KNIP, ['exports|src/a.ts|unused', 'exports|src/c.ts|other', 'types|src/b.ts|Unused']);

    const result = run('--base', baseSha, KNIP);

    assert.equal(result.status, 1);
    assert.match(result.output, /exports: 1 -> 2 {2}<-- grew/);
});

test('fails when a new rule appears in a baseline without a config change', () => {
    write(ESLINT, {
        'src/a.js': { 'sw-core-rules/no-javascript-files': { count: 1 }, complexity: { count: 2 } },
        'src/b.js': { 'sw-core-rules/no-javascript-files': { count: 1 }, 'max-params': { count: 1 } },
    });

    const result = run('--base', baseSha, '--config', CONFIG, ESLINT);

    assert.equal(result.status, 1);
    assert.match(result.output, /max-params: 0 -> 1 {2}<-- grew/);
});

test('passes a new rule when the config changes too, and marks it', () => {
    writeFileSync(join(repo, CONFIG), 'export default [{ rules: { "max-params": "error" } }];');
    write(ESLINT, {
        'src/a.js': { 'sw-core-rules/no-javascript-files': { count: 1 }, complexity: { count: 2 } },
        'src/b.js': { 'sw-core-rules/no-javascript-files': { count: 1 }, 'max-params': { count: 1 } },
    });

    const result = run('--base', baseSha, '--config', CONFIG, ESLINT);

    assert.equal(result.status, 0);
    assert.match(result.output, /max-params: 0 -> 1 {2}<-- new rule/);
});

test('fails when an existing rule grows, even with a config change', () => {
    writeFileSync(join(repo, CONFIG), 'export default [{ rules: { "max-params": "error" } }];');
    write(ESLINT, {
        'src/a.js': { 'sw-core-rules/no-javascript-files': { count: 1 }, complexity: { count: 3 } },
        'src/b.js': { 'sw-core-rules/no-javascript-files': { count: 1 } },
    });

    const result = run('--base', baseSha, '--config', CONFIG, ESLINT);

    assert.equal(result.status, 1);
    assert.match(result.output, /complexity: 2 -> 3 {2}<-- grew/);
});

test('passes when a file moves without new violations', () => {
    rmSync(join(repo, 'admin/src/a.js'));
    touch('admin/src/moved/a.js');
    write(ESLINT, {
        'src/moved/a.js': { 'sw-core-rules/no-javascript-files': { count: 1 }, complexity: { count: 2 } },
        'src/b.js': { 'sw-core-rules/no-javascript-files': { count: 1 } },
    });

    assert.equal(run('--base', baseSha, ESLINT).status, 0);
});

test('passes a baseline that does not exist on the base commit and marks it as new', () => {
    const storefront = 'storefront/eslint-suppressions.json';
    touch('storefront/src/main.js');
    write(storefront, { 'src/main.js': { 'sw-core-rules/no-javascript-files': { count: 1 } } });

    const result = run('--base', baseSha, storefront);

    assert.equal(result.status, 0);
    assert.match(result.output, /new baseline, not on the base commit/);
    assert.match(result.output, /no-javascript-files: - -> 1\n/);
});

test('fails on entries of a deleted file, which would hide a new violation of the same rule', () => {
    rmSync(join(repo, 'admin/src/b.js'));

    const result = run('--base', baseSha, ESLINT);

    assert.equal(result.status, 1);
    assert.match(result.output, /no longer exist. Run `npm run lint:fix`/);
    assert.match(result.output, /admin\/eslint-suppressions.json: src\/b.js/);
});

test('fails when the base commit cannot be read', () => {
    const result = run('--base', '0000000000000000000000000000000000000000', ESLINT);

    assert.notEqual(result.status, 0);
    assert.match(result.output, /Could not read the base commit/);
});

test('fails without a base commit or baseline files', () => {
    assert.equal(run('--base', baseSha).status, 2);
    assert.equal(run(ESLINT).status, 2);
});
