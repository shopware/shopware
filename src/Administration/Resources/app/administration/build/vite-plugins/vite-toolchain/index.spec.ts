/**
 * @sw-package framework
 */
import fs from 'fs';
import os from 'os';
import path from 'path';
import { getViteCommand, isVite8ToolchainActive, loadViteToolchain, VITE_8_TOOLCHAIN_PATH } from './index';

const vite8NodeModules = path.join(VITE_8_TOOLCHAIN_PATH, 'node_modules');

describe('build/vite-plugins/vite-toolchain', () => {
    const originalProjectRoot = process.env.PROJECT_ROOT;
    let projectRoot: string;

    function dumpFeatureFlags(flags: Record<string, boolean>): void {
        fs.mkdirSync(path.join(projectRoot, 'var'), { recursive: true });
        fs.writeFileSync(path.join(projectRoot, 'var', 'config_js_features.json'), JSON.stringify(flags));
    }

    beforeEach(() => {
        projectRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'vite-toolchain-'));
        process.env.PROJECT_ROOT = projectRoot;
    });

    afterEach(() => {
        fs.rmSync(projectRoot, { recursive: true, force: true });

        if (originalProjectRoot === undefined) {
            delete process.env.PROJECT_ROOT;
        } else {
            process.env.PROJECT_ROOT = originalProjectRoot;
        }

        jest.restoreAllMocks();
    });

    it('uses Vite 6 when the feature flags are not dumped', () => {
        expect(isVite8ToolchainActive()).toBe(false);
        expect(getViteCommand()).toBe('vite');
    });

    it('uses Vite 6 when the ADMIN_VITE_8 feature flag is inactive', () => {
        dumpFeatureFlags({ ADMIN_VITE_8: false, V6_8_0_0: false });

        expect(isVite8ToolchainActive()).toBe(false);
        expect(getViteCommand()).toBe('vite');
    });

    it('uses the Vite 8 binary of the toolchain when the ADMIN_VITE_8 feature flag is active', () => {
        dumpFeatureFlags({ ADMIN_VITE_8: true });

        expect(isVite8ToolchainActive()).toBe(true);
        expect(getViteCommand()).toBe(`node "${path.join(vite8NodeModules, 'vite', 'bin', 'vite.js')}"`);
        expect(fs.existsSync(path.join(vite8NodeModules, 'vite', 'bin', 'vite.js'))).toBe(true);
    });

    it('explains how to install the toolchain when the ADMIN_VITE_8 feature flag is active and it is missing', async () => {
        dumpFeatureFlags({ ADMIN_VITE_8: true });
        const existsSync = fs.existsSync;
        jest.spyOn(fs, 'existsSync').mockImplementation((filePath) => {
            return filePath === path.join(vite8NodeModules, 'vite') ? false : existsSync(filePath);
        });

        await expect(loadViteToolchain()).rejects.toThrow(
            `The ADMIN_VITE_8 feature flag is active, but the Vite 8 toolchain is not installed. Run "npm ci" in "${VITE_8_TOOLCHAIN_PATH}".`,
        );
    });
});
