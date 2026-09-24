/**
 * @sw-package framework
 */

import vue from '@vitejs/plugin-vue';
import type { Plugin } from 'vite';
import shopwareSetupPlugin from '../shopware-setup';

type Options = {
    /** Compiles templates with Vue 2 compat behaviour, which extension templates still rely on. */
    vue2TemplateCompat?: boolean;
};

/**
 * @private
 *
 * The SFC pipeline shared by the Administration and extension builds, so both compile `.vue` files
 * the same way.
 */
export default function vueSfcPlugins({ vue2TemplateCompat = false }: Options = {}): Plugin[] {
    return [
        shopwareSetupPlugin(),
        vue(vue2TemplateCompat ? { template: { compilerOptions: { compatConfig: { MODE: 2 } } } } : {}),
    ];
}
