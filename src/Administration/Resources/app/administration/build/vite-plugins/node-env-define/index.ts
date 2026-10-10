/**
 * @sw-package framework
 */
import { transformWithOxc } from 'vite';
import type { Plugin } from 'vite';

/**
 * @private
 *
 * In the bundled dev mode, vite-plugin-node-polyfills injects `import process from '.../shims/process'` into the
 * source modules. Vite replaces `process.env.NODE_ENV` only afterwards and skips it then, because `process` is no global
 * identifier anymore. This plugin replaces it before the polyfills are injected, like it happens in production builds.
 */
export default function NodeEnvDefinePlugin(): Plugin {
    let nodeEnv = JSON.stringify('development');

    return {
        name: 'shopware-vite-plugin-node-env-define',

        apply: 'serve',

        configResolved(config) {
            nodeEnv =
                (config.define?.['process.env.NODE_ENV'] as string | undefined) ??
                JSON.stringify(process.env.NODE_ENV || config.mode);
        },

        transform: {
            filter: {
                id: { exclude: /\/node_modules\// },
                code: 'process.env.NODE_ENV',
            },
            async handler(code, id) {
                const result = await transformWithOxc(code, id, {
                    lang: 'js',
                    sourcemap: true,
                    define: { 'process.env.NODE_ENV': nodeEnv },
                });

                return { code: result.code, map: result.map };
            },
        },
    };
}
