/**
 * @sw-package framework
 */

import * as fs from 'node:fs';
import * as os from 'node:os';
import * as path from 'node:path';
import { runCli } from './cli';
import { EXTRACTOR_VERSION, type ApiSnapshot } from './snapshot/types';

const FIXTURE_ROOT = path.join(__dirname, '__fixtures__', 'admin-root');

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

describe('api-explorer cli', () => {
    let tmpDir: string;

    beforeEach(() => {
        tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'api-explorer-'));
    });

    afterEach(() => {
        fs.rmSync(tmpDir, { recursive: true, force: true });
    });

    it('prints a pretty-printed working tree snapshot to stdout', async () => {
        const before = Date.now();
        const { exitCode, stdout, stderr } = await run('snapshot', '--root', FIXTURE_ROOT);

        expect(exitCode).toBe(0);
        expect(stderr).toBe('');
        expect(stdout).toMatch(/^\{\n {4}"formatVersion": 1,/);

        const snapshot = JSON.parse(stdout) as ApiSnapshot;

        expect(snapshot).toMatchObject({
            formatVersion: 1,
            extractorVersion: EXTRACTOR_VERSION,
            ref: 'WORKTREE',
            commit: null,
            shopwareObject: { path: 'Shopware', name: 'Shopware' },
        });
        expect(Array.isArray(snapshot.components)).toBe(true);
        expect(Array.isArray(snapshot.shopwareObject.children)).toBe(true);
        expect(Array.isArray(snapshot.diagnostics)).toBe(true);
        expect(new Date(snapshot.createdAt).toISOString()).toBe(snapshot.createdAt);
        expect(Date.parse(snapshot.createdAt)).toBeGreaterThanOrEqual(before - 1000);
    });

    it('records the given ref and commit and writes to the --out file', async () => {
        const outFile = path.join(tmpDir, 'nested', 'snapshot.json');
        const commit = '0123456789abcdef0123456789abcdef01234567';
        const { exitCode, stdout } = await run(
            'snapshot',
            '--root',
            FIXTURE_ROOT,
            '--ref',
            'v6.6.10.29',
            '--commit',
            commit,
            '--out',
            outFile,
        );

        expect(exitCode).toBe(0);
        expect(stdout).toBe('');

        const snapshot = JSON.parse(fs.readFileSync(outFile, 'utf8')) as ApiSnapshot;

        expect(snapshot.ref).toBe('v6.6.10.29');
        expect(snapshot.commit).toBe(commit);
    });

    it.each([
        [[]],
        [['unknown']],
        [['toString']],
        [['snapshot', '--unknown']],
        [['snapshot', '--root']],
        [['snapshot', '--ref', '']],
        [['snapshot', 'extra']],
    ])('exits with 2 and prints the usage for invalid arguments %j', async (argv) => {
        const { exitCode, stdout, stderr } = await run(...argv);

        expect(exitCode).toBe(2);
        expect(stdout).toBe('');
        expect(stderr).toContain('Usage: api-explorer');
    });

    it('exits with 1 when the admin root does not exist', async () => {
        const { exitCode, stderr } = await run('snapshot', '--root', path.join(tmpDir, 'missing'));

        expect(exitCode).toBe(1);
        expect(stderr).toContain('Admin root is not a directory');
    });
});
