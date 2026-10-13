/**
 * @sw-package framework
 *
 * @private
 *
 * Vite 8 and its plugins live in the own package `build/vite-8`, so every import of `vite` from these plugins resolves
 * to Vite 8 instead of the Vite 6 of the Administration.
 *
 * @deprecated tag:v6.8.0 - Will be removed. Vite 8 becomes the regular dependency of the Administration.
 */
import fs from 'fs';
import path from 'path';
import { createRequire } from 'module';
import type * as Vite from 'vite';
import { readFeatureFlags } from '../utils';
import type vue from '@vitejs/plugin-vue';
import type { nodePolyfills } from 'vite-plugin-node-polyfills';
import type symfonyPlugin from 'vite-plugin-symfony';

/**
 * @private
 *
 * Typed with the Vite 6 types of the Administration, because TypeScript cannot resolve the ESM only types of Vite 8.
 */
export type ViteToolchain = {
    isVite8: boolean;
    vite: typeof Vite;
    vue: typeof vue;
    nodePolyfills: typeof nodePolyfills;
    symfonyPlugin: typeof symfonyPlugin;
};

/** @private */
export const VITE_8_TOOLCHAIN_PATH = path.resolve(__dirname, '../../vite-8');

const FEATURE_FLAG = 'ADMIN_VITE_8';

/** @private */
export function isVite8ToolchainActive(): boolean {
    return readFeatureFlags()[FEATURE_FLAG] === true;
}

/**
 * @private
 *
 * Extension bundles need the `__rolldown_runtime__` of the bundled core, so the core and the plugins use the same condition.
 */
export function isBundledDevActive(isDev: boolean): boolean {
    return isDev && process.env.SHOPWARE_ADMIN_BUNDLED_DEV === '1' && isVite8ToolchainActive();
}

/** @private */
export async function loadViteToolchain(): Promise<ViteToolchain> {
    if (isVite8ToolchainActive()) {
        return loadVite8Toolchain();
    }

    // Dynamic imports keep Vite 6 out of the Vite 8 processes: the config bundlers of Vite keep them as runtime imports
    // and ts-node compiles them to lazy requires.
    const [
        vite,
        vuePlugin,
        symfony,
        polyfills,
    ] = await Promise.all([
        import('vite'),
        import('@vitejs/plugin-vue'),
        import('vite-plugin-symfony'),
        import('vite-plugin-node-polyfills'),
    ]);

    return {
        isVite8: false,
        vite,
        vue: vuePlugin.default,
        symfonyPlugin: symfony.default,
        nodePolyfills: polyfills.nodePolyfills,
    };
}

function loadVite8Toolchain(): ViteToolchain {
    if (!fs.existsSync(path.join(VITE_8_TOOLCHAIN_PATH, 'node_modules', 'vite'))) {
        throw new Error(
            `The ${FEATURE_FLAG} feature flag is active, but the Vite 8 toolchain is not installed. Run "npm ci" in "${VITE_8_TOOLCHAIN_PATH}".`,
        );
    }

    // `createRequire` resolves the packages from the toolchain and keeps the bundler of the Vite config from inlining
    // them. Requiring the ESM only packages works with the Node.js versions Vite 8 supports. An ESM entry file in the
    // toolchain would not work, because ts-node only lets Node itself require ESM files inside of `node_modules`.
    const toolchainRequire = createRequire(path.join(VITE_8_TOOLCHAIN_PATH, 'package.json'));

    /* eslint-disable @typescript-eslint/no-unsafe-member-access */
    return {
        isVite8: true,
        vite: toolchainRequire('vite') as ViteToolchain['vite'],
        vue: toolchainRequire('@vitejs/plugin-vue').default as ViteToolchain['vue'],
        nodePolyfills: toolchainRequire('vite-plugin-node-polyfills').nodePolyfills as ViteToolchain['nodePolyfills'],
        symfonyPlugin: toolchainRequire('vite-plugin-symfony').default as ViteToolchain['symfonyPlugin'],
    };
    /* eslint-enable @typescript-eslint/no-unsafe-member-access */
}

/** @private */
export function getViteBinPath(): string {
    const viteRoot = isVite8ToolchainActive()
        ? path.join(VITE_8_TOOLCHAIN_PATH, 'node_modules', 'vite')
        : path.dirname(require.resolve('vite/package.json'));

    return path.join(viteRoot, 'bin', 'vite.js');
}

/** @private */
export function getViteCommand(): string {
    return `node "${getViteBinPath()}"`;
}
