/**
 * @sw-package framework
 */

import * as path from 'node:path';
import type * as Vite from 'vite';
import type { UiFactory } from './create-explorer-server';

const API_EXPLORER_ROOT = path.resolve(__dirname, '..');
export const APP_ROOT = path.join(API_EXPLORER_ROOT, 'app');
const ADMIN_ROOT = path.resolve(API_EXPLORER_ROOT, '..', '..');

/** Settings shared by the dev server and the static report build. */
export const SHARED_VITE_CONFIG: Vite.InlineConfig = {
    configFile: false,
    envFile: false,
    root: APP_ROOT,
    clearScreen: false,
    resolve: {
        // Templates are compiled in the browser, so the build with the compiler is needed.
        alias: [
            {
                find: /^vue$/,
                replacement: path.join(ADMIN_ROOT, 'node_modules', 'vue', 'dist', 'vue.esm-bundler.js'),
            },
        ],
    },
    define: {
        __VUE_OPTIONS_API__: 'true',
        __VUE_PROD_DEVTOOLS__: 'false',
        __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: 'false',
    },
    css: { postcss: {} },
};

export async function loadVite(): Promise<typeof Vite> {
    // ts-node compiles this to `require`, which loads Vite's CommonJS entry.
    process.env.VITE_CJS_IGNORE_WARNING ??= 'true';

    return import('vite');
}

export const createViteUi: UiFactory = async (httpServer) => {
    const { createServer } = await loadVite();

    const vite = await createServer({
        ...SHARED_VITE_CONFIG,
        appType: 'spa',
        logLevel: 'warn',
        server: {
            middlewareMode: true,
            hmr: { server: httpServer },
            fs: { allow: [ADMIN_ROOT] },
        },
        optimizeDeps: { include: ['vue'] },
    });

    return {
        middleware: (request, response, next) => vite.middlewares(request, response, next),
        close: () => vite.close(),
    };
};
