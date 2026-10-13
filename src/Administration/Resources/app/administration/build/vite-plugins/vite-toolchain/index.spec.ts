/**
 * @sw-package framework
 */
import { execFileSync } from 'child_process';
import fs from 'fs';
import os from 'os';
import path from 'path';
import { getViteCommand, isVite8ToolchainActive, loadVite8Toolchain, VITE_8_TOOLCHAIN_PATH } from './index';

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
        process.env.PROJECT_ROOT = originalProjectRoot;
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

    it('explains how to install the toolchain when it is missing', () => {
        const existsSync = fs.existsSync;
        jest.spyOn(fs, 'existsSync').mockImplementation((filePath) => {
            return filePath === path.join(vite8NodeModules, 'vite') ? false : existsSync(filePath);
        });

        expect(() => loadVite8Toolchain()).toThrow(
            `The ADMIN_VITE_8 feature flag is active, but the Vite 8 toolchain is not installed. Run "npm ci" in "${VITE_8_TOOLCHAIN_PATH}".`,
        );
    });

    it.each([
        '@vitejs/plugin-basic-ssl',
        '@vitejs/plugin-vue',
        'vite-plugin-node-polyfills',
        'vite-plugin-symfony',
    ])('resolves the runtime import of vite in %s to Vite 8', (plugin) => {
        const resolvedVite = require.resolve('vite', { paths: [path.join(vite8NodeModules, plugin)] });

        expect(resolvedVite.startsWith(path.join(vite8NodeModules, 'vite') + path.sep)).toBe(true);
    });

    it('keeps Vite 6 as the vite of the Administration', () => {
        const resolvedVite = require.resolve('vite/package.json');

        expect(resolvedVite.startsWith(vite8NodeModules)).toBe(false);
        expect((JSON.parse(fs.readFileSync(resolvedVite, 'utf8')) as { version: string }).version).toMatch(/^6\./);
    });

    it('loads the Vite 8 toolchain', () => {
        // Vite 8 is published as ESM only, which Jest cannot load, so the toolchain is loaded in a separate Node process.
        // It runs from a file, because node-stdlib-browser breaks with the global `require` of `node -e`.
        const scriptPath = path.join(projectRoot, 'load-toolchain.cjs');
        fs.writeFileSync(
            scriptPath,
            `
            const { createJiti } = require(${JSON.stringify(require.resolve('jiti'))});
            const jiti = createJiti(${JSON.stringify(__filename)});
            const { loadVite8Toolchain } = jiti(${JSON.stringify(path.join(__dirname, 'index.ts'))});
            const toolchain = loadVite8Toolchain();

            process.stdout.write(JSON.stringify({
                viteVersion: toolchain.vite.version,
                exports: Object.fromEntries(Object.entries(toolchain).map(([name, value]) => [name, typeof value])),
            }));
            `,
        );

        const result = JSON.parse(
            execFileSync(process.execPath, [scriptPath], {
                cwd: path.resolve(__dirname, '../../..'),
                encoding: 'utf8',
            }),
        ) as { viteVersion: string; exports: Record<string, string> };

        expect(result.viteVersion).toMatch(/^8\./);
        expect(result.exports).toEqual({
            basicSsl: 'function',
            nodePolyfills: 'function',
            svgLoader: 'function',
            symfonyPlugin: 'function',
            vite: 'object',
            vue: 'function',
        });
    });
});
