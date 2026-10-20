import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { afterEach, beforeEach, describe, test } from 'node:test';
import { findForbiddenDirectives } from './check-lint-directives.ts';

const SCRIPT = join(dirname(fileURLToPath(import.meta.url)), 'check-lint-directives.ts');

const lines = (source: string): number[] => findForbiddenDirectives(source).map(({ line }) => line);

describe('findForbiddenDirectives', () => {
    test('reports a block disable that names an eslint-comments rule', () => {
        assert.deepEqual(
            lines('/* eslint-disable @eslint-community/eslint-comments/no-restricted-disable, no-console */\n'),
            [1],
        );
    });

    test('reports line and next-line disables that name an eslint-comments rule', () => {
        const source = [
            'console.log(1); // eslint-disable-line @eslint-community/eslint-comments/no-restricted-disable, no-console',
            '// eslint-disable-next-line @eslint-community/eslint-comments/no-use -- needed here',
            '/* eslint no-console: "off" */',
        ].join('\n');

        assert.deepEqual(lines(source), [1, 2, 3]);
    });

    test('reports a disable that spans several lines of a block comment', () => {
        assert.deepEqual(lines('const a = 1;\n/*\n  eslint-disable\n  eslint-comments/no-use\n*/\n'), [2]);
    });

    test('reports a rule config comment', () => {
        assert.deepEqual(lines('const a = 1;\n/* eslint no-console: "off" */\n'), [2]);
    });

    test('reports a directive after a string that looks like an open comment', () => {
        assert.deepEqual(
            lines("const glob = 'src/*.ts';\n/* eslint-disable @eslint-community/eslint-comments/no-use */\n"),
            [2],
        );
    });

    test('allows disables of other rules, also with a reason that mentions eslint-comments', () => {
        const source = [
            '/* eslint-disable */',
            '// eslint-disable-next-line no-console -- not eslint-comments/no-use',
            'console.log(1); // eslint-disable-line no-console',
            '/* eslint-enable @eslint-community/eslint-comments/no-use */',
        ].join('\n');

        assert.deepEqual(lines(source), []);
    });

    test('allows comments that only mention eslint', () => {
        const source = [
            '/**',
            ' * eslint config: see eslint.config.ts',
            ' */',
            '// eslint no-console: "off" only works in block comments',
            "const pattern = './**/*.ts';",
            '/* global Shopware */',
        ].join('\n');

        assert.deepEqual(lines(source), []);
    });
});

describe('check-lint-directives.ts', () => {
    let repo: string;

    const write = (path: string, content: string): void => {
        mkdirSync(join(repo, dirname(path)), { recursive: true });
        writeFileSync(join(repo, path), content);
    };

    const track = (): void => {
        execFileSync('git', ['add', '.'], { cwd: repo });
    };

    const run = (...args: string[]) => {
        const result = spawnSync(process.execPath, [SCRIPT, ...args], { cwd: repo, encoding: 'utf8' });

        return { status: result.status, output: `${result.stdout}${result.stderr}` };
    };

    beforeEach(() => {
        repo = mkdtempSync(join(tmpdir(), 'lint-directives-'));
        execFileSync('git', ['init', '--quiet'], { cwd: repo });
    });

    afterEach(() => {
        rmSync(repo, { recursive: true, force: true });
    });

    test('passes and prints how many files it scanned', () => {
        write('admin/src/a.ts', '// eslint-disable-next-line no-console -- debugging aid\nconsole.log(1);\n');
        write('admin/src/b.js', 'export default 1;\n');
        write('admin/src/readme.md', '/* eslint no-console: "off" */\n');
        track();

        const result = run('admin');

        assert.equal(result.status, 0);
        assert.match(result.output, /Scanned 2 files in admin\./);
    });

    test('fails and names the file and line of a forbidden directive', () => {
        write('admin/src/a.ts', 'const a = 1;\n/* eslint-disable @eslint-community/eslint-comments/no-use */\n');
        write('storefront/src/b.js', 'export default 1;\n');
        track();

        const result = run('admin', 'storefront');

        assert.equal(result.status, 1);
        assert.match(result.output, /admin\/src\/a\.ts:2: Do not disable the eslint-comments rules/);
        assert.match(result.output, /Scanned 1 files in storefront\./);
    });

    test('ignores files that git does not track', () => {
        write('admin/src/a.ts', 'export default 1;\n');
        track();
        write('admin/node_modules/x/index.js', '/* eslint no-console: "off" */\n');

        assert.equal(run('admin').status, 0);
    });

    test('fails when a directory is missing', () => {
        const result = run('missing');

        assert.equal(result.status, 2);
        assert.match(result.output, /missing is no directory/);
    });

    test('fails when a directory has no tracked source files', () => {
        write('admin/src/a.ts', 'export default 1;\n');

        const result = run('admin');

        assert.equal(result.status, 2);
        assert.match(result.output, /No tracked source files in admin/);
    });
});
