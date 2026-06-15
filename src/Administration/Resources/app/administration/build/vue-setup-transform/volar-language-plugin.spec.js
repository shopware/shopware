/**
 * @sw-package framework
 */

import shopwareSetupVolarPlugin from './volar-language-plugin';
import { stripIndent } from './index.spec/helpers';

describe('build/vue-setup-transform/volar-language-plugin', () => {
    it('returns undefined for Vue files without Shopware setup syntax', () => {
        const plugin = shopwareSetupVolarPlugin();
        const source = stripIndent`
            <template>{{ count }}</template>
            <script setup>
            const count = 1;
            </script>
        `;

        const result = plugin.parseSFC('plain.vue', source);

        expect(result).toBeUndefined();
    });

    it('parses Shopware setup syntax into the transformed SFC shape used by language tooling', () => {
        const plugin = shopwareSetupVolarPlugin();
        const source = stripIndent`
            <template>{{ count }}</template>
            <script setup lang="ts" sw-component="sw-language-server-fixture">
            const count = 1;
            
            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = plugin.parseSFC('shopware-setup.vue', source);

        expect(result).toBeDefined();
        expect(result.descriptor.scriptSetup.attrs).toEqual({
            setup: true,
            lang: 'ts',
        });
        expect(result.descriptor.scriptSetup.content).toContain(
            'Shopware.Component.createScriptSetupExtendableComponent()',
        );
        expect(result.descriptor.scriptSetup.content).not.toContain('swDefinePublic');
    });
});
