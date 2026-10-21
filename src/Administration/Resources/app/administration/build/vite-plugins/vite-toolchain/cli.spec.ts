/**
 * @sw-package framework
 */
import { execFileSync } from 'child_process';
import fs from 'fs';
import os from 'os';
import path from 'path';

const administrationRoot = path.resolve(__dirname, '../../..');

describe('build/vite-plugins/vite-toolchain/cli', () => {
    let projectRoot: string;

    function runViteVersion(flags: Record<string, boolean>): string {
        fs.mkdirSync(path.join(projectRoot, 'var'), { recursive: true });
        fs.writeFileSync(path.join(projectRoot, 'var', 'config_js_features.json'), JSON.stringify(flags));

        return execFileSync(
            process.execPath,
            [
                require.resolve('ts-node/dist/bin.js'),
                '-T',
                path.join(__dirname, 'cli.ts'),
                '--version',
            ],
            {
                cwd: administrationRoot,
                env: { ...process.env, PROJECT_ROOT: projectRoot },
                encoding: 'utf8',
            },
        );
    }

    beforeEach(() => {
        projectRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'vite-toolchain-cli-'));
    });

    afterEach(() => {
        fs.rmSync(projectRoot, { recursive: true, force: true });
    });

    it('runs the Vite 6 CLI when the ADMIN_VITE_8 feature flag is inactive', () => {
        expect(runViteVersion({ ADMIN_VITE_8: false })).toMatch(/^vite\/6\./);
    });

    it('runs the Vite 8 CLI when the ADMIN_VITE_8 feature flag is active', () => {
        expect(runViteVersion({ ADMIN_VITE_8: true })).toMatch(/^vite\/8\./);
    });
});
