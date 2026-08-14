/**
 * @sw-package framework
 */

import * as fs from 'fs';
import * as os from 'os';
import * as path from 'path';
import { spawnSync } from 'child_process';

describe('scripts/codemods/sfc-migration CLI', () => {
    let tmpDir: string;

    beforeEach(() => {
        tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'sfc-cli-'));
    });

    afterEach(() => {
        fs.rmSync(tmpDir, { recursive: true, force: true });
    });

    function runCli(...args: string[]): { status: number | null; output: string } {
        const result = spawnSync(
            process.execPath,
            [
                '-r',
                'ts-node/register/transpile-only',
                path.join(__dirname, 'run-sfc-migration.ts'),
                ...args,
            ],
            { cwd: process.cwd(), encoding: 'utf8' },
        );

        return { status: result.status, output: `${result.stdout ?? ''}${result.stderr ?? ''}` };
    }

    it.each([
        [
            'unknown flag',
            '--unknown',
        ],
        [
            'replacement without write',
            '--replace-originals',
        ],
        [
            'duplicate write',
            '--write',
            '--write',
        ],
    ])('rejects %s with a nonzero exit code', (_label, ...flags: string[]) => {
        const result = runCli(tmpDir, ...flags);

        expect(result.status).toBe(1);
        expect(result.output).toContain('Usage: npm run codemod:sfc-migration');
    });

    it('returns zero for a successful read-only empty scan', () => {
        const result = runCli(tmpDir);

        expect(result.status).toBe(0);
        expect(result.output).toContain('dry run — nothing written');
    });

    it('returns nonzero when the target is not a directory', () => {
        const file = path.join(tmpDir, 'not-a-directory.js');

        fs.writeFileSync(file, 'export default {};\n');

        const result = runCli(file);

        expect(result.status).toBe(1);
        expect(result.output).toContain('Not a directory:');
    });
});
