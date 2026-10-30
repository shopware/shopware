/**
 * @sw-package framework
 */

/**
 * Covers Vue macros the transform rejects: `defineExpose()`, which the transform generates itself
 * (nested calls stay untouched, like compiler-sfc), and base-only macros used in override mode.
 */

import { stripIndent, transformOrFail, transformShopwareSetupSfc } from './helpers';

describe('build/vue-setup-transform unsupported macros', () => {
    it('rejects an unsupported Vue macro', () => {
        const source = stripIndent`
            <script setup>
            defineExpose({});
            const count = 1;
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'macro.vue')).toThrow(
            'defineExpose() is not supported inside Shopware setup blocks.',
        );
    });

    it('ignores nested unsupported Vue macros like compiler-sfc does', () => {
        const source = stripIndent`
            <script setup>
            function createExposeArgument() {
                return defineExpose({});
            }

            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'nested-unsupported-macro.vue').code;

        // The enclosing function is renamed as a top-level binding, but the nested defineExpose() call
        // is a function-local and stays untouched (never rejected as a top-level unsupported macro).
        expect(result).toContain('return defineExpose({});');
    });

    it('rejects defineProps() in override mode', () => {
        const source = stripIndent`
            <script setup>
            const props = defineProps();
            swDefineOverride({});
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'override-props.override.vue')).toThrow(
            'defineProps() is only supported in base Shopware setup blocks.',
        );
    });

    it('rejects withDefaults() in override mode', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const props = withDefaults(defineProps<{ label?: string }>(), {
                label: 'fallback',
            });
            swDefineOverride({});
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'override-props-with-defaults.override.vue')).toThrow(
            'withDefaults() is only supported in base Shopware setup blocks.',
        );
    });

    it('rejects defineEmits() in override mode', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const emit = defineEmits(['save']);
            swDefineOverride({});
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'override-emits.override.vue')).toThrow(
            'defineEmits() is only supported in base Shopware setup blocks.',
        );
    });

    // An override cannot be told to use swDefinePublic(): that marker is itself rejected in override
    // mode, so the advice would only swap one error for the next.
    it('sends an override authoring defineExpose() to swDefineOverride(), not swDefinePublic()', () => {
        const source = stripIndent`
            <script setup lang="ts">
            defineExpose({});
            swDefineOverride({});
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'override-expose.override.vue')).toThrow(
            'Declare replacement bindings with swDefineOverride({ ... }) instead.',
        );
    });

    it('points an authored defineExpose() at swDefinePublic() instead', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const count = 1;
            defineExpose({ count });
            swDefinePublic({ count });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'base-expose.vue')).toThrow(
            'Use swDefinePublic({ ... }) instead, which will call it for you automatically.',
        );
    });

    it('rejects defineSlots() in override mode', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const slots = defineSlots();
            swDefineOverride({});
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'override-slots.override.vue')).toThrow(
            'defineSlots() is only supported in base Shopware setup blocks.',
        );
    });

    it('rejects defineModel() in override mode', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const model = defineModel<string>();
            swDefineOverride({ model });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'override-model.override.vue')).toThrow(
            'defineModel() is only supported in base Shopware setup blocks.',
        );
    });

    it('rejects defineOptions() in override mode', () => {
        const source = stripIndent`
            <script setup lang="ts">
            defineOptions({ inheritAttrs: false });
            swDefineOverride({});
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'override-options.override.vue')).toThrow(
            'defineOptions() is only supported in base Shopware setup blocks.',
        );
    });
});
