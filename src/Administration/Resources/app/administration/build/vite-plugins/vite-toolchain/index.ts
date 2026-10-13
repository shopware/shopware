/**
 * @sw-package framework
 *
 * @private
 *
 * Switches the Administration build between Vite 6 and Vite 8 with the `ADMIN_VITE_8` feature flag.
 *
 * Vite 6 stays the regular dependency of the Administration. Vite 8 and the Vite plugins it needs are installed as an
 * own package in `build/vite-8` (see the `postinstall` script), because npm aliases like `"vite-8": "npm:vite@8"` in
 * the `package.json` of the Administration are not enough:
 *
 * - Plugins like `@vitejs/plugin-vue`, `vite-plugin-symfony` and `vite-plugin-node-polyfills`
 *   import `vite` at runtime. Next to an alias, Node still resolves this import to the Vite 6 of the Administration,
 *   so a Vite 8 build would run its plugins with the internals of Vite 6.
 * - The Vite 8 versions of these plugins would need aliases, too. npm hoists them to the same `node_modules` as Vite 6,
 *   so they resolve `vite` to Vite 6 as well.
 * - The `vite` binary always belongs to the package installed as `vite`.
 *
 * In its own package, every import of `vite` from the Vite 8 toolchain resolves to `build/vite-8/node_modules/vite`.
 * Node only falls back to the `node_modules` of the Administration for dependencies the toolchain does not install
 * itself, like `vue`.
 *
 * @deprecated tag:v6.8.0 - Will be removed. Vite 8 becomes the regular dependency of the Administration.
 */
import fs from 'fs';
import path from 'path';
import { createRequire } from 'module';
import type * as Vite from 'vite';
import type vue from '@vitejs/plugin-vue';
import type { nodePolyfills } from 'vite-plugin-node-polyfills';
import type symfonyPlugin from 'vite-plugin-symfony';
import type svgLoader from 'vite-svg-loader';

/**
 * @private
 *
 * Typed with the Vite 6 types of the Administration, because TypeScript cannot resolve the ESM only types of Vite 8.
 */
export type Vite8Toolchain = {
    vite: typeof Vite & {
        transformWithOxc: (
            code: string,
            filename: string,
            options: { lang: 'js'; sourcemap: boolean; define: Record<string, string> },
        ) => Promise<{ code: string; map: Vite.Rollup.SourceMapInput }>;
    };
    vue: typeof vue;
    basicSsl: (options: { name: string; domains: string[] }) => Vite.Plugin;
    nodePolyfills: typeof nodePolyfills;
    symfonyPlugin: typeof symfonyPlugin;
    svgLoader: typeof svgLoader;
};

/** @private */
export const VITE_8_TOOLCHAIN_PATH = path.resolve(__dirname, '../../vite-8');

const FEATURE_FLAG = 'ADMIN_VITE_8';

/** @private */
export function isVite8ToolchainActive(): boolean {
    const projectRoot = process.env.PROJECT_ROOT || path.resolve(__dirname, '../../../../../../../../');
    const flagsPath = path.join(projectRoot, 'var', 'config_js_features.json');

    if (!fs.existsSync(flagsPath)) {
        return false;
    }

    const featureFlags = JSON.parse(fs.readFileSync(flagsPath, 'utf-8')) as Record<string, boolean>;

    return featureFlags[FEATURE_FLAG] === true;
}

/** @private */
export function loadVite8Toolchain(): Vite8Toolchain {
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
        vite: toolchainRequire('vite') as Vite8Toolchain['vite'],
        vue: toolchainRequire('@vitejs/plugin-vue').default as Vite8Toolchain['vue'],
        basicSsl: toolchainRequire('@vitejs/plugin-basic-ssl').default as Vite8Toolchain['basicSsl'],
        nodePolyfills: toolchainRequire('vite-plugin-node-polyfills').nodePolyfills as Vite8Toolchain['nodePolyfills'],
        symfonyPlugin: toolchainRequire('vite-plugin-symfony').default as Vite8Toolchain['symfonyPlugin'],
        svgLoader: toolchainRequire('vite-svg-loader').default as Vite8Toolchain['svgLoader'],
    };
    /* eslint-enable @typescript-eslint/no-unsafe-member-access */
}

/** @private */
export function getViteCommand(): string {
    if (!isVite8ToolchainActive()) {
        return 'vite';
    }

    return `node "${path.join(VITE_8_TOOLCHAIN_PATH, 'node_modules', 'vite', 'bin', 'vite.js')}"`;
}
