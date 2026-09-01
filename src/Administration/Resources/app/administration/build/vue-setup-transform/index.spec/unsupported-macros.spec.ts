/**
 * @sw-package framework
 */

/**
 * Covers Vue macros the transform rejects: unsupported macros such as `defineModel()` and
 * `defineExpose()` (nested calls stay untouched, like compiler-sfc), and base-only macros used in
 * override mode.
 */

import { stripIndent, transformOrFail, transformShopwareSetupSfc } from './helpers';

describe('build/vue-setup-transform unsupported macros', () => {
    it.each([
        [
            'defineModel()',
            'Vue macro defineModel() is not supported inside Shopware setup blocks.',
        ],
        [
            'defineExpose({})',
            'defineExpose() is not supported inside Shopware setup blocks.',
        ],
    ])('rejects unsupported Vue macro %s', (macro, expectedMessage) => {
        const source = stripIndent`
            <script setup>
            ${macro};
            const count = 1;
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'macro.vue')).toThrow(expectedMessage);
    });

    it('ignores nested unsupported Vue macros like compiler-sfc does', () => {
        const source = stripIndent`
            <script setup>
            function createModel() {
                return defineModel();
            }

            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'nested-unsupported-macro.vue').code;

        // The enclosing function is renamed as a top-level binding, but the nested defineModel() call
        // is a function-local and stays untouched (never rejected as a top-level unsupported macro).
        expect(result).toContain('return defineModel();');
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

    it('rejects defineExpose() in override mode', () => {
        const source = stripIndent`
            <script setup lang="ts">
            defineExpose({});
            swDefineOverride({});
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'override-expose.override.vue')).toThrow(
            'defineExpose() is not supported inside Shopware setup blocks.',
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
            'List the binding in swDefinePublic({ ... }) instead.',
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
