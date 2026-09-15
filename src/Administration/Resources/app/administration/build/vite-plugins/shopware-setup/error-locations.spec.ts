/**
 * @sw-package framework
 */

import shopwareSetupPlugin from './index';
import { stripIndent } from '../../vue-setup-transform/index.spec/helpers';

describe('Shopware setup diagnostics through Vite', () => {
    it('provides the original component location and code frame', async () => {
        const plugin = shopwareSetupPlugin({ administrationRoot: process.cwd() });
        const transform = plugin.transform as (code: string, id: string) => Promise<unknown>;
        const source = stripIndent`
            <template>
                <div />
            </template>
            <script setup>
            const broken = { a: 1 b: 2 };
            swDefinePublic({});
            </script>
        `;

        const result = transform(source, '/example/sw-broken.vue');

        await expect(result).rejects.toHaveProperty('loc', {
            file: '/example/sw-broken.vue',
            line: 5,
            column: 22,
        });
        await expect(result).rejects.toHaveProperty('frame', expect.stringContaining('5  |  const broken = { a: 1 b: 2 };'));
    });
});
