import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { afterEach, beforeEach, test } from 'node:test';

const SCRIPT = join(dirname(fileURLToPath(import.meta.url)), 'check-baseline-growth.ts');
const BASELINE = 'admin/eslint-suppressions.json';
const CONFIG = 'admin/eslint.config.ts';
const FALLOW_BASELINE = 'admin/fallow-baseline.json';

let repo: string;
let baseSha: string;

const git = (...args: string[]): string => execFileSync('git', args, { cwd: repo, encoding: 'utf8' }).trim();

const write = (path: string, content: string): void => {
    mkdirSync(join(repo, dirname(path)), { recursive: true });
    writeFileSync(join(repo, path), content);
};

const writeBaseline = (suppressions: Record<string, Record<string, number>>): void => {
    const entries = Object.entries(suppressions).map(([file, rules]) => [
        file,
        Object.fromEntries(Object.entries(rules).map(([rule, count]) => [rule, { count }])),
    ]);
    write(BASELINE, JSON.stringify(Object.fromEntries(entries)));
};

const writeFallowBaseline = (findings: Record<string, string[]>): void => {
    write(FALLOW_BASELINE, JSON.stringify({ kind: 'dead-code', unused_files: [], ...findings }));
};

const check = (baseline = BASELINE) => {
    const result = spawnSync(process.execPath, [SCRIPT, '--base', baseSha, '--config', CONFIG, baseline], {
        cwd: repo,
        encoding: 'utf8',
    });

    return { status: result.status, output: `${result.stdout}${result.stderr}` };
};

beforeEach(() => {
    repo = mkdtempSync(join(tmpdir(), 'baseline-growth-'));
    git('init', '--quiet');
    git('config', 'user.email', 'test@example.com');
    git('config', 'user.name', 'Test');

    write('admin/src/a.js', '');
    write('admin/src/b.js', '');
    write(CONFIG, 'export default [];');
    writeBaseline({ 'src/a.js': { complexity: 2 }, 'src/b.js': { complexity: 1 } });
    writeFallowBaseline({ unused_exports: ['unused-export:src/a.ts:x', 'unused-export:src/b.ts:y'] });
    git('add', '.');
    git('commit', '--quiet', '-m', 'base');
    baseSha = git('rev-parse', 'HEAD');
});

afterEach(() => {
    rmSync(repo, { recursive: true, force: true });
});

test('passes when a baseline shrinks', () => {
    writeBaseline({ 'src/a.js': { complexity: 1 } });

    const result = check();

    assert.equal(result.status, 0);
    assert.match(result.output, /complexity: 3 -> 1\n/);
    assert.match(result.output, /Compared 1 baseline\(s\)/);
});

test('fails when a rule total grows', () => {
    writeBaseline({ 'src/a.js': { complexity: 3 }, 'src/b.js': { complexity: 1 } });

    const result = check();

    assert.equal(result.status, 1);
    assert.match(result.output, /complexity: 3 -> 4 {2}<-- grew/);
});

test('fails when a new rule appears without a config change', () => {
    writeBaseline({ 'src/a.js': { complexity: 2, 'max-params': 1 }, 'src/b.js': { complexity: 1 } });

    const result = check();

    assert.equal(result.status, 1);
    assert.match(result.output, /max-params: 0 -> 1 {2}<-- grew/);
});

test('passes a new rule when the config changes too', () => {
    write(CONFIG, 'export default [{ rules: { "max-params": "error" } }];');
    writeBaseline({ 'src/a.js': { complexity: 2, 'max-params': 1 }, 'src/b.js': { complexity: 1 } });

    const result = check();

    assert.equal(result.status, 0);
    assert.match(result.output, /max-params: 0 -> 1\n/);
});

test('fails on entries of a deleted file', () => {
    rmSync(join(repo, 'admin/src/b.js'));

    const result = check();

    assert.equal(result.status, 1);
    assert.match(result.output, /src\/b.js no longer exists. Run `npm run lint:fix`/);
});

test('passes when a Fallow baseline shrinks', () => {
    writeFallowBaseline({ unused_exports: ['unused-export:src/a.ts:x'] });

    const result = check(FALLOW_BASELINE);

    assert.equal(result.status, 0);
    assert.match(result.output, /unused_exports: 2 -> 1\n/);
});

test('fails when a Fallow finding category grows', () => {
    writeFallowBaseline({
        unused_exports: ['unused-export:src/a.ts:x', 'unused-export:src/b.ts:y'],
        unused_files: ['unused-file:src/c.ts'],
    });

    const result = check(FALLOW_BASELINE);

    assert.equal(result.status, 1);
    assert.match(result.output, /unused_files: 0 -> 1 {2}<-- grew/);
});
