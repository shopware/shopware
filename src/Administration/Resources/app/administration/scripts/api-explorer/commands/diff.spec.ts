/**
 * @sw-package framework
 */

import * as fs from 'node:fs';
import * as os from 'node:os';
import * as path from 'node:path';
import { runCli } from '../cli';
import { component, prop, snapshot } from '../diff/create-diff.spec/builders';
import type { ApiDiff } from '../diff/types';
import type { ApiSnapshot } from '../snapshot/types';

async function run(...argv: string[]): Promise<{ exitCode: number; stdout: string; stderr: string }> {
    let stdout = '';
    let stderr = '';
    const exitCode = await runCli(argv, {
        stdout: (text) => {
            stdout += text;
        },
        stderr: (text) => {
            stderr += text;
        },
    });

    return { exitCode, stdout, stderr };
}

describe('api-explorer diff command', () => {
    let tmpDir: string;
    let basePath: string;
    let compatiblePath: string;
    let breakingPath: string;

    function write(name: string, content: ApiSnapshot | string): string {
        const file = path.join(tmpDir, name);
        fs.writeFileSync(file, typeof content === 'string' ? content : JSON.stringify(content));

        return file;
    }

    beforeEach(() => {
        tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'api-explorer-diff-'));
        basePath = write(
            'base.json',
            snapshot({ ref: 'trunk', components: [component('sw-foo', { props: [prop('value')] })] }),
        );
        compatiblePath = write(
            'compatible.json',
            snapshot({ components: [component('sw-foo', { props: [prop('value'), prop('size')] })] }),
        );
        breakingPath = write('breaking.json', snapshot({ components: [component('sw-foo')] }));
    });

    afterEach(() => {
        fs.rmSync(tmpDir, { recursive: true, force: true });
    });

    it('prints the diff as pretty JSON and exits with 0', async () => {
        const { exitCode, stdout, stderr } = await run('diff', '--base', basePath, '--head', compatiblePath);

        expect(exitCode).toBe(0);
        expect(stderr).toBe('');
        expect(stdout).toMatch(/^\{\n {4}"base": \{/);

        const diff = JSON.parse(stdout) as ApiDiff;

        expect(diff.base.ref).toBe('trunk');
        expect(diff.changes).toEqual([expect.objectContaining({ id: 'component:sw-foo:prop:size', severity: 'added' })]);
    });

    it('exits with 0 for breaking changes without --fail-on-breaking', async () => {
        const { exitCode, stdout } = await run('diff', '--base', basePath, '--head', breakingPath);

        expect(exitCode).toBe(0);
        expect((JSON.parse(stdout) as ApiDiff).summary.hasBreaking).toBe(true);
    });

    it('exits with 3 for breaking changes with --fail-on-breaking', async () => {
        const { exitCode } = await run('diff', '--base', basePath, '--head', breakingPath, '--fail-on-breaking');

        expect(exitCode).toBe(3);
    });

    it('exits with 0 with --fail-on-breaking when nothing breaks', async () => {
        const { exitCode } = await run('diff', '--base', basePath, '--head', compatiblePath, '--fail-on-breaking');

        expect(exitCode).toBe(0);
    });

    it('writes the diff to the --out file', async () => {
        const outFile = path.join(tmpDir, 'nested', 'diff.json');
        const { exitCode, stdout } = await run(
            'diff',
            '--base',
            basePath,
            '--head',
            breakingPath,
            '--out',
            outFile,
            '--fail-on-breaking',
        );

        expect(exitCode).toBe(3);
        expect(stdout).toBe('');
        expect((JSON.parse(fs.readFileSync(outFile, 'utf8')) as ApiDiff).summary.bySeverity.breaking).toBe(1);
    });

    it.each([
        [[]],
        [['--head', 'head.json']],
        [['--base', 'base.json']],
        [
            [
                '--base',
                '',
                '--head',
                'head.json',
            ],
        ],
        [
            [
                '--base',
                'base.json',
                '--head',
                'head.json',
                '--out',
                '',
            ],
        ],
        [
            [
                '--base',
                'base.json',
                '--head',
                'head.json',
                'extra',
            ],
        ],
        [
            [
                '--base',
                'base.json',
                '--head',
                'head.json',
                '--unknown',
            ],
        ],
        [['--base']],
    ])('exits with 2 and prints the usage for invalid arguments %j', async (args) => {
        const { exitCode, stdout, stderr } = await run('diff', ...args);

        expect(exitCode).toBe(2);
        expect(stdout).toBe('');
        expect(stderr).toContain('Usage: api-explorer diff');
    });

    it('exits with 1 when a snapshot file is missing', async () => {
        const { exitCode, stderr } = await run('diff', '--base', path.join(tmpDir, 'missing.json'), '--head', basePath);

        expect(exitCode).toBe(1);
        expect(stderr).toContain('missing.json');
    });

    it.each([
        ['not json', '{'],
        ['not a snapshot', JSON.stringify({ hello: 'world' })],
        ['another format version', JSON.stringify({ ...snapshot(), formatVersion: 2 })],
    ])('exits with 1 when a snapshot file is %s', async (_, content) => {
        const { exitCode, stdout } = await run('diff', '--base', basePath, '--head', write('invalid.json', content));

        expect(exitCode).toBe(1);
        expect(stdout).toBe('');
    });
});
