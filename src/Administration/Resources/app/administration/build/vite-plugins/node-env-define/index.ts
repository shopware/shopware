/**
 * @sw-package framework
 */
import type { Plugin } from 'vite';
import MagicString from 'magic-string';

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

        enforce: 'pre',

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
            handler(code, id) {
                const magicString = new MagicString(code);
                magicString.replaceAll(/\bprocess\.env\.NODE_ENV\b/g, nodeEnv);

                if (!magicString.hasChanged()) {
                    return null;
                }

                return {
                    code: magicString.toString(),
                    map: magicString.generateMap({ source: id, hires: true }),
                };
            },
        },
    };
}
