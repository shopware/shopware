/**
 * @sw-package framework
 */

import { stripIndent, transformOrFail, transformShopwareSetupSfc } from './helpers';

describe('build/vue-setup-transform validation', () => {
    it('transforms plain native script setup blocks using filename metadata', () => {
        const source = stripIndent`
            <script setup>
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'sw-native.vue');

        expect(result.code).toContain('Shopware.Component.createExtendableSetup(');
        expect(result.code).toContain("name: 'sw-native'");
    });

    it('transforms independently from template attributes', () => {
        const source = stripIndent`
            <template>
                <div></div>
            </template>
            <script setup>
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'template-attribute.vue');

        expect(result.code).toContain("name: 'template-attribute'");
    });

    it('keeps the Vue script setup range when an attribute value contains a script-like string', () => {
        const source = stripIndent`
            <script setup data-example="<script">
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'script-attribute.vue').code;

        expect(result).toContain('Shopware.Component.createExtendableSetup(');
        expect(result).toContain("name: 'script-attribute'");
    });

    it('preserves script setup attributes that do not belong to the Shopware transform', () => {
        const source = stripIndent`
            <script setup lang="ts" generic="TValue" future-flag>
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'passthrough-attributes.vue').code;

        expect(result).toContain('<script setup lang="ts" generic="TValue" future-flag>');
    });

    it.each([
        [
            'defineModel()',
            'Vue macro defineModel() is not supported inside Shopware setup blocks.',
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

        expect(result).toContain(`function createModel() {
            return defineModel();
        }`);
        expect(result).not.toContain('Vue macro defineModel() is not supported');
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
            'defineExpose() is only supported in base Shopware setup blocks.',
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

    it('rejects top-level await', () => {
        const source = stripIndent`
            <script setup>
            const value = await loadValue();
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'await.vue')).toThrow(
            'Top-level await is not supported inside Shopware setup blocks.',
        );
    });

    it('rejects useSwProps() in base mode', () => {
        const source = stripIndent`
            <script setup>
            const props = useSwProps();
            const count = props.initialCount ?? 0;

            swDefinePublic({
                count,
            });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'base-use-sw-props.vue')).toThrow(
            "useSwProps() is only supported in override Shopware setup blocks. Base components must use Vue's defineProps() macro instead.",
        );
    });

    it('rejects useSwPreviousState() in base mode', () => {
        const source = stripIndent`
            <script setup>
            const previousState = useSwPreviousState();
            const count = 1;

            swDefinePublic({
                count,
            });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'base-previous-state.vue')).toThrow(
            'useSwPreviousState() is only supported in override Shopware setup blocks.',
        );
    });

    it('hoists ambient declare declarations to the generated script root', () => {
        const source = stripIndent`
            <script setup lang="ts">
            declare const injected: number;
            const count = injected + 1;
            swDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'declare.vue').code;

        // Like Vue, ambient declarations describe runtime values provided from elsewhere: they stay at the
        // script root, are referenced from the callback, but are never collected as returned setup state.
        expect(result).toContain('declare const injected: number;');
        expect(result.indexOf('declare const injected')).toBeLessThan(result.indexOf('createExtendableSetup('));
        expect(result).toContain('const count = injected + 1;');
        expect(result).not.toMatch(/\n\s*injected,/);
    });

    it('rejects ES module exports like native script setup', () => {
        const source = stripIndent`
            <script setup>
            export const count = 1;
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'export.vue')).toThrow(
            '<script setup> cannot contain ES module exports.',
        );
    });

    it('ignores non-setup script blocks', () => {
        const source = stripIndent`
            <script>
            const count = 1;
            </script>
        `;

        expect(transformShopwareSetupSfc(source, 'normal-script.vue')).toBeNull();
    });

    it('rejects an additional normal script block next to Shopware setup', () => {
        const source = stripIndent`
            <script>
            export const moduleValue = 1;
            </script>
            <script setup>
            const count = 1;
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'two-scripts.vue')).toThrow(
            'A Shopware setup block cannot be combined with another <script> block',
        );
    });

    it.each([
        [
            'swDefinePublic({ [dynamicKey]: count });',
            'swDefinePublic() only supports shorthand bindings such as { a, b }.',
        ],
        [
            'swDefinePublic({ public: count });',
            'swDefinePublic() only supports shorthand bindings such as { a, b }.',
        ],
        [
            "swDefinePublic({ 'public': count });",
            'swDefinePublic() only supports shorthand bindings such as { a, b }.',
        ],
        [
            'swDefinePublic({ ...publicState });',
            'Spread properties are not supported inside swDefinePublic().',
        ],
        [
            'swDefinePublic(publicState);',
            'swDefinePublic() requires exactly one object-literal argument.',
        ],
        [
            'if (true) { swDefinePublic({ count }); }',
            'swDefinePublic() must be called once at the top level',
        ],
        [
            'const __swOverride = {}; swDefinePublic({ __swOverride });',
            '"__swOverride" is reserved for Shopware override-private state and cannot be exposed with swDefinePublic().',
        ],
    ])('rejects invalid swDefinePublic usage: %s', (publicMarker, expectedMessage) => {
        const source = stripIndent`
            <script setup>
            const count = 1;
            ${publicMarker}
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'public.vue')).toThrow(expectedMessage);
    });

    it.each([
        [
            'swDefineOverride({ [dynamicKey]: count });',
            'swDefineOverride() only supports shorthand bindings such as { a, b }.',
        ],
        [
            'swDefineOverride({ override: count });',
            'swDefineOverride() only supports shorthand bindings such as { a, b }.',
        ],
        [
            "swDefineOverride({ 'override': count });",
            'swDefineOverride() only supports shorthand bindings such as { a, b }.',
        ],
        [
            'swDefineOverride({ ...overrideState });',
            'Spread properties are not supported inside swDefineOverride().',
        ],
        [
            'swDefineOverride(overrideState);',
            'swDefineOverride() requires exactly one object-literal argument.',
        ],
        [
            'if (true) { swDefineOverride({ count }); }',
            'swDefineOverride() must be called once at the top level',
        ],
        [
            'swDefineOverride({ count, count });',
            'Duplicate override Shopware setup binding key "count".',
        ],
        [
            'const __swOverride = {}; swDefineOverride({ __swOverride });',
            '"__swOverride" is reserved for Shopware override-private state and cannot be exposed with swDefineOverride().',
        ],
    ])('rejects invalid swDefineOverride usage: %s', (overrideMarker, expectedMessage) => {
        const source = stripIndent`
            <script setup>
            const count = 1;
            ${overrideMarker}
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'override.override.vue')).toThrow(expectedMessage);
    });

    it('requires swDefineOverride() in override mode', () => {
        const source = stripIndent`
            <script setup>
            const count = 1;
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'missing-override.override.vue')).toThrow(
            'swDefineOverride() must be called exactly once at the top level of an override Shopware setup block.',
        );
    });

    it('rejects swDefineOverride() in base mode', () => {
        const source = stripIndent`
            <script setup>
            const count = 1;
            swDefineOverride({ count });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'base-override.vue')).toThrow(
            'swDefineOverride() is a Shopware setup compile-time macro for override components. ' +
                'It declares which base component bindings this override replaces. ' +
                'Base components must use swDefinePublic() to expose overrideable setup bindings instead.',
        );
    });

    it('rejects top-level bindings using the reserved __swSetup prefix', () => {
        const source = stripIndent`
            <script setup>
            const __swSetupProps = 1;
            const count = __swSetupProps;
            swDefinePublic({ count });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'reserved-prefix.vue')).toThrow(
            '"__swSetupProps" uses the reserved "__swSetup" prefix of the Shopware setup transform and must not be declared or imported.',
        );
    });

    it('rejects imported and unknown swDefineOverride() bindings', () => {
        const source = stripIndent`
            <script setup>
            import { computed } from 'vue';

            const count = 1;

            swDefineOverride({
                computed,
                missing,
            });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'override-import.override.vue')).toThrow(
            'Imported binding "computed" cannot be exposed with swDefineOverride().',
        );
    });

    it('ignores fake Shopware setup script tags in non-top-level contexts', () => {
        const source = stripIndent`
            <!-- <script setup></script> -->
            <template>
                <div data-example="<script setup>"></div>
            </template>
            <style>
            .example::before { content: "<script setup>"; }
            </style>
            <script setup>
            // <script setup>
            /* <script setup> */
            const single = '<script setup>';
            const fake = "<script setup>";
            const template = \`<script setup>\${'<script setup>'}\`;
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'scanner.vue').code;

        expect(result).toContain('Shopware.Component.createExtendableSetup(');
        expect(result).toContain("name: 'scanner'");
    });

    it('skips transformation when Vue reports SFC parse errors', () => {
        const source = stripIndent`
            <template>
                <div>
            </template>
            <script setup>
            const count = 1;
        `;

        expect(transformShopwareSetupSfc(source, 'malformed.vue')).toBeNull();
    });
});
