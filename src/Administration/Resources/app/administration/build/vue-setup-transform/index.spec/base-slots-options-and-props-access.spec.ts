/**
 * @sw-package framework
 */

import {
    expectVueCompilerScriptToCompile,
    stripIndent,
    transformOrFail,
    transformShopwareSetupSfc,
} from './helpers';

describe('build/vue-setup-transform base slots, options, and props access', () => {
    it('keeps base defineSlots() outside the extendable setup callback and replaces it with context.slots', () => {
        const source = stripIndent`
            <script setup lang="ts" sw-component="sw-my-component">
            const slots = defineSlots<{
                default(props: { count: number }): unknown;
            }>();
            
            function renderDefaultSlot() {
                return slots.default?.({ count: 1 });
            }
            
            const count = 1;
            
            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-slots.vue').code;

        expect(result).toContain(`const slots = defineSlots<{
    default(props: { count: number }): unknown;
}>();`);
        expect(result).toContain('const slots = (__shopwareContext.slots);');
        expect(result).toContain('return slots.default?.({ count: 1 });');
        expect(result).toContain('private: {\n                renderDefaultSlot,\n            }');
        expect(result.match(/defineSlots/g)).toHaveLength(1);
    });

    it('keeps local slot type declarations available for hoisted defineSlots()', () => {
        const source = stripIndent`
            <script setup lang="ts" sw-component="sw-my-component">
            type Slots = {
                default(props: { count: number }): unknown;
            };

            const slots = defineSlots<Slots>();
            const count = slots.default ? 1 : 0;

            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-slots-local-type.vue').code;

        expect(result.indexOf('type Slots')).toBeLessThan(result.indexOf('const slots = defineSlots<Slots>()'));
        expect(result.indexOf('type Slots')).toBeLessThan(
            result.indexOf('Shopware.Component.createExtendableSetup('),
        );
        expectVueCompilerScriptToCompile(result, 'base-slots-local-type.vue');
    });

    it('replaces defineSlots() destructuring inside the extendable setup callback', () => {
        const source = stripIndent`
            <script setup sw-component="sw-my-component">
            const { default: defaultSlot } = defineSlots();
            const count = defaultSlot ? 1 : 0;
            
            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-destructured-slots.vue').code;

        expect(result).toContain('const slots = defineSlots();');
        expect(result).toContain('const { default: defaultSlot } = (__shopwareContext.slots);');
    });

    it('keeps bare defineSlots() outside the callback when the generated slots binding name is taken', () => {
        const source = stripIndent`
            <script setup sw-component="sw-my-component">
            defineSlots();
            
            function slots() {
                return 'local binding';
            }
            
            const count = slots().length;
            
            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-bare-slots-collision.vue').code;

        expect(result).toContain('const slots2 = defineSlots();');
        expect(result).toContain('(__shopwareContext.slots);');
        expect(result).toContain("return 'local binding';");
        expect(result).toContain('private: {\n                slots,\n            }');
    });

    it('keeps base defineOptions() outside the extendable setup callback', () => {
        const source = stripIndent`
            <script setup sw-component="sw-my-component">
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
        expect(result.indexOf('defineOptions({')).toBeLessThan(
            result.indexOf('Shopware.Component.createExtendableSetup('),
        );
        expect(result).not.toContain(`(__shopwareSetupBindings) => {
    const useSwContext = () => __shopwareContext;

    defineOptions`);
        expect(result.match(/defineOptions/g)).toHaveLength(1);
    });

    it('preserves defineOptions() component name and custom options at the generated script root', () => {
        const source = stripIndent`
            <script setup sw-component="sw-my-component">
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
        expect(result).not.toContain(`customOption: {
        enabled: true,
    },

    const count`);
    });

    it('keeps static local option values available for hoisted defineOptions()', () => {
        const source = stripIndent`
            <script setup sw-component="sw-my-component">
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

        const result = transformOrFail(source, 'base-options-local-value.vue').code;

        expect(result.indexOf('const inheritAttrs = false;')).toBeLessThan(
            result.indexOf('defineOptions({'),
        );
        expect(result).toContain('private: {\n                inheritAttrs,\n            }');
        expectVueCompilerScriptToCompile(result, 'base-options-local-value.vue');
    });

    it('supports defineOptions() wrapped in a TypeScript as expression', () => {
        const source = stripIndent`
            <script setup lang="ts" sw-component="sw-my-component">
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
        expect(result.indexOf('defineOptions({')).toBeLessThan(
            result.indexOf('Shopware.Component.createExtendableSetup('),
        );
        expect(result.match(/defineOptions/g)).toHaveLength(1);
    });

    it('rejects duplicate base defineSlots() declarations', () => {
        const source = stripIndent`
            <script setup sw-component="sw-my-component">
            const slots = defineSlots();
            const otherSlots = defineSlots();
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'base-duplicate-slots.vue')).toThrow(
            'Only one defineSlots() call is allowed in a base Shopware setup block.',
        );
    });

    it('rejects duplicate base defineOptions() declarations', () => {
        const source = stripIndent`
            <script setup sw-component="sw-my-component">
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

    it('ignores nested defineSlots() like Vue compiler-sfc does', () => {
        const source = stripIndent`
            <script setup lang="ts" sw-component="sw-my-component">
            function render() {
                return defineSlots<{ default(): unknown }>();
            }

            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'base-nested-slots.vue').code;

        expect(result).toContain(`function render() {
            return defineSlots<{ default(): unknown }>();
        }`);
        expect(result).not.toContain('const slots = defineSlots');
        expect(result).not.toContain('(__shopwareContext.slots)');
    });

    it('rewrites props access by source ranges instead of placeholder string replacement', () => {
        const source = stripIndent`
            <script setup sw-component="sw-my-component">
            const props = defineProps();
            const literal = '__SHOPWARE_SETUP_DEFINE_PROPS__';
            const count = props.initialCount ?? literal.length;
            
            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-props-placeholder-literal.vue').code;

        expect(result).toContain("const literal = '__SHOPWARE_SETUP_DEFINE_PROPS__';");
        expect(result).toContain('const props = (__shopwareProps);');
    });
});
