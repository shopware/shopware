/**
 * @sw-package framework
 */
import fs from 'fs';
import os from 'os';
import path from 'path';
import {
    getViteBinPath,
    getViteCommand,
    isBundledDevActive,
    isVite8ToolchainActive,
    loadViteToolchain,
    VITE_8_TOOLCHAIN_PATH,
} from './index';

const vite8NodeModules = path.join(VITE_8_TOOLCHAIN_PATH, 'node_modules');
const vite6Bin = path.join(path.dirname(require.resolve('vite/package.json')), 'bin', 'vite.js');

describe('build/vite-plugins/vite-toolchain', () => {
    const originalProjectRoot = process.env.PROJECT_ROOT;
    const originalBundledDev = process.env.SHOPWARE_ADMIN_BUNDLED_DEV;
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

        if (originalBundledDev === undefined) {
            delete process.env.SHOPWARE_ADMIN_BUNDLED_DEV;
        } else {
            process.env.SHOPWARE_ADMIN_BUNDLED_DEV = originalBundledDev;
        }

        jest.restoreAllMocks();
    });

    it('uses Vite 6 when the feature flags are not dumped', () => {
        expect(isVite8ToolchainActive()).toBe(false);
        expect(getViteBinPath()).toBe(vite6Bin);
        expect(getViteCommand()).toBe(`node "${vite6Bin}"`);
    });

    it('uses Vite 6 when the ADMIN_VITE_8 feature flag is inactive', () => {
        dumpFeatureFlags({ ADMIN_VITE_8: false, V6_8_0_0: false });

        expect(isVite8ToolchainActive()).toBe(false);
        expect(getViteBinPath()).toBe(vite6Bin);
        expect(getViteCommand()).toBe(`node "${vite6Bin}"`);
    });

    it('uses the Vite 8 binary of the toolchain when the ADMIN_VITE_8 feature flag is active', () => {
        dumpFeatureFlags({ ADMIN_VITE_8: true });

        expect(isVite8ToolchainActive()).toBe(true);
        expect(getViteBinPath()).toBe(path.join(vite8NodeModules, 'vite', 'bin', 'vite.js'));
        expect(getViteCommand()).toBe(`node "${path.join(vite8NodeModules, 'vite', 'bin', 'vite.js')}"`);
        expect(fs.existsSync(path.join(vite8NodeModules, 'vite', 'bin', 'vite.js'))).toBe(true);
    });

    describe('when the ADMIN_VITE_8 feature flag is active and the toolchain is missing', () => {
        const missingToolchainError = `The ADMIN_VITE_8 feature flag is active, but the Vite 8 toolchain is not installed. Run "npm ci" in "${VITE_8_TOOLCHAIN_PATH}".`;

        beforeEach(() => {
            dumpFeatureFlags({ ADMIN_VITE_8: true });
            const existsSync = fs.existsSync;
            jest.spyOn(fs, 'existsSync').mockImplementation((filePath) => {
                return filePath === path.join(vite8NodeModules, 'vite') ? false : existsSync(filePath);
            });
        });

        it('explains how to install the toolchain when loading it', async () => {
            await expect(loadViteToolchain()).rejects.toThrow(missingToolchainError);
        });

        it('explains how to install the toolchain before the Vite CLI is started', () => {
            expect(() => getViteBinPath()).toThrow(missingToolchainError);
            expect(() => getViteCommand()).toThrow(missingToolchainError);
        });
    });

    it.each([
        { isDev: false, bundledDev: '1', vite8: true, core: false, extensions: false },
        { isDev: false, bundledDev: 'core', vite8: true, core: false, extensions: false },
        { isDev: true, bundledDev: undefined, vite8: true, core: false, extensions: false },
        { isDev: true, bundledDev: '0', vite8: true, core: false, extensions: false },
        { isDev: true, bundledDev: 'true', vite8: true, core: false, extensions: false },
        { isDev: true, bundledDev: '1', vite8: false, core: false, extensions: false },
        { isDev: true, bundledDev: 'core', vite8: false, core: false, extensions: false },
        { isDev: true, bundledDev: '1', vite8: true, core: true, extensions: true },
        { isDev: true, bundledDev: 'core', vite8: true, core: true, extensions: false },
    ])('selects bundled delivery for the host and extensions: %o', ({ isDev, bundledDev, vite8, core, extensions }) => {
        dumpFeatureFlags({ ADMIN_VITE_8: vite8 });

        if (bundledDev === undefined) {
            delete process.env.SHOPWARE_ADMIN_BUNDLED_DEV;
        } else {
            process.env.SHOPWARE_ADMIN_BUNDLED_DEV = bundledDev;
        }

        expect(isBundledDevActive(isDev)).toBe(core);
        expect(isBundledDevActive(isDev, 'core')).toBe(core);
        expect(isBundledDevActive(isDev, 'extensions')).toBe(extensions);
    });
});
