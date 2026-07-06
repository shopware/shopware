/**
 * @sw-package framework
 */

import { expectVueCompilerScriptToCompile, stripIndent, transformOrFail, transformShopwareSetupSfc } from './helpers';

describe('build/vue-setup-transform base defineSlots macro', () => {
    it('keeps base defineSlots() outside the extendable setup callback and replaces it with context.slots', () => {
        const source = stripIndent`
            <script setup lang="ts">
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

        expect(result).toContain(`defineSlots<{
    default(props: { count: number }): unknown;
}>();`);
        expect(result).not.toContain('const slots = defineSlots');
        expect(result).toContain('const slots = (__swSetupContext.slots);');
        expect(result).toContain('return slots.default?.({ count: 1 });');
        expect(result).toContain('private: {\n                slots,\n                renderDefaultSlot,\n            }');
        expect(result.match(/defineSlots/g)).toHaveLength(1);
    });

    it('keeps local slot type declarations available for hoisted defineSlots()', () => {
        const source = stripIndent`
            <script setup lang="ts">
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

        expect(result.indexOf('type Slots')).toBeLessThan(result.indexOf('defineSlots<Slots>()'));
        expect(result.indexOf('type Slots')).toBeLessThan(result.indexOf('Shopware.Component.createExtendableSetup('));
        expectVueCompilerScriptToCompile(result, 'base-slots-local-type.vue');
    });

    it('replaces defineSlots() destructuring inside the extendable setup callback', () => {
        const source = stripIndent`
            <script setup>
            const { default: defaultSlot } = defineSlots();
            const count = defaultSlot ? 1 : 0;

            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-destructured-slots.vue').code;

        expect(result).toContain('defineSlots();');
        expect(result).toContain('const { default: defaultSlot } = (__swSetupContext.slots);');
    });

    it('hoists bare defineSlots() statements', () => {
        const source = stripIndent`
            <script setup>
            defineSlots();

            const count = 1;

            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-bare-slots.vue').code;

        expect(result).toContain('defineSlots();');
        expect(result).not.toContain('const slots = defineSlots');
        expect(result).toContain('(__swSetupContext.slots);');
    });

    it('rejects duplicate base defineSlots() declarations', () => {
        const source = stripIndent`
            <script setup>
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

    it('ignores nested defineSlots() like Vue compiler-sfc does', () => {
        const source = stripIndent`
            <script setup lang="ts">
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
        expect(result).not.toContain('(__swSetupContext.slots)');
    });
});
