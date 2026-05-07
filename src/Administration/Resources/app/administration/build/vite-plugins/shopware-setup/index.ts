/**
 * @sw-package framework
 */

import type { Plugin } from 'vite';
import { transformShopwareSetupSfc } from '../../vue-setup-transform';

/**
 * @private
 *
 * Runs before @vitejs/plugin-vue so Vue only ever sees standard SFC syntax.
 * Parser-sensitive behavior stays in build/vue-setup-transform for reuse by Jest,
 * ESLint, and editor tooling.
 */
export default function ShopwareSetupPlugin(): Plugin {
    return {
        name: 'shopware-vite-plugin-shopware-setup',
        enforce: 'pre',

        transform(code, id) {
            const fileName = id.split('?')[0];

            if (!fileName.endsWith('.vue')) {
                return null;
            }

            const result = transformShopwareSetupSfc(code, fileName);

            if (!result) {
                return null;
            }

            return {
                code: result.code,
                map: result.map,
            };
        },
    };
}
