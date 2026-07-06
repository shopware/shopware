/**
 * @sw-package framework
 */

import { stripIndent, transformOrFail, transformShopwareSetupSfc } from './helpers';

describe('build/vue-setup-transform base defineOptions macro', () => {
    it('keeps base defineOptions() outside the extendable setup callback', () => {
        const source = stripIndent`
            <script setup>
            defineOptions({
                inheritAttrs: false,
            });

            const count = 1;

            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-options.vue').code;

        expect(result).toContain(`defineOptions({
    inheritAttrs: false,
});`);
        expect(result.indexOf('defineOptions({')).toBeLessThan(result.indexOf('Shopware.Component.createExtendableSetup('));
        expect(result.match(/defineOptions/g)).toHaveLength(1);
    });

    it('preserves defineOptions() component name and custom options at the generated script root', () => {
        const source = stripIndent`
            <script setup>
            defineOptions({
                name: 'sw-custom-name',
                inheritAttrs: false,
                customOption: {
                    enabled: true,
                },
            });

            const count = 1;

            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-options-custom.vue').code;

        expect(result).toContain(`defineOptions({
    name: 'sw-custom-name',
    inheritAttrs: false,
    customOption: {
        enabled: true,
    },
});`);
        expect(result.indexOf("name: 'sw-custom-name'")).toBeLessThan(
            result.indexOf('Shopware.Component.createExtendableSetup('),
        );
    });

    it('rejects local setup bindings in hoisted defineOptions() arguments', () => {
        const source = stripIndent`
            <script setup>
            const inheritAttrs = false;
            defineOptions({
                inheritAttrs,
            });

            const count = 1;

            swDefinePublic({
                count,
            });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'base-options-local-value.vue')).toThrow(
            'defineOptions() arguments are hoisted outside the Shopware setup callback and must not reference local setup bindings.',
        );
    });

    it('supports defineOptions() wrapped in a TypeScript as expression', () => {
        const source = stripIndent`
            <script setup lang="ts">
            defineOptions({
                inheritAttrs: false,
            }) as void;

            const count = 1;

            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-options-as.vue').code;

        expect(result).toContain(`defineOptions({
    inheritAttrs: false,
});`);
        expect(result).not.toContain('as void');
        expect(result.indexOf('defineOptions({')).toBeLessThan(result.indexOf('Shopware.Component.createExtendableSetup('));
        expect(result.match(/defineOptions/g)).toHaveLength(1);
    });

    it('rejects duplicate base defineOptions() declarations', () => {
        const source = stripIndent`
            <script setup>
            defineOptions({ inheritAttrs: false });
            defineOptions({ name: 'sw-my-component' });
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'base-duplicate-options.vue')).toThrow(
            'Only one defineOptions() call is allowed in a base Shopware setup block.',
        );
    });

    it('ignores nested defineOptions() like Vue compiler-sfc does', () => {
        const source = stripIndent`
            <script setup>
            if (true) {
                defineOptions({ inheritAttrs: false });
            }
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'nested-options.vue').code;

        expect(result).toContain(`if (true) {
            defineOptions({ inheritAttrs: false });
        }`);
        expect(result.indexOf('defineOptions({ inheritAttrs: false })')).toBeGreaterThan(
            result.indexOf('Shopware.Component.createExtendableSetup('),
        );
    });
});
