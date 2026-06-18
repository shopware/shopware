/**
 * @sw-package framework
 */

import { stripIndent, transformOrFail, transformShopwareSetupSfc } from './helpers';

describe('build/vue-setup-transform validation', () => {
    it('ignores plain native script setup blocks', () => {
        const source = stripIndent`
            <script setup>
            const count = 1;
            </script>
        `;

        expect(transformShopwareSetupSfc(source, 'native.vue')).toBeNull();
    });

    it('ignores Shopware attributes outside script setup blocks', () => {
        const source = stripIndent`
            <template>
                <div sw-component="sw-my-component"></div>
            </template>
            <script setup>
            const count = 1;
            </script>
        `;

        expect(transformShopwareSetupSfc(source, 'template-attribute.vue')).toBeNull();
    });

    it('keeps the Vue script setup range when an attribute value contains a script-like string', () => {
        const source = stripIndent`
            <script setup sw-component="sw-my-component" data-example="<script">
            const count = 1;
            </script>
        `;

        const result = transformOrFail(source, 'script-attribute.vue').code;

        expect(result).toContain("Shopware.Component.createScriptSetupExtendableComponent()('sw-my-component'");
    });

    it('preserves script setup attributes that do not belong to the Shopware transform', () => {
        const source = stripIndent`
            <script setup lang="ts" sw-component="sw-my-component" generic="TValue" future-flag>
            const count = 1;
            </script>
        `;

        const result = transformOrFail(source, 'passthrough-attributes.vue').code;

        expect(result).toContain('<script setup lang="ts" generic="TValue" future-flag>');
        expect(result).not.toContain('sw-component=');
    });

    it('preserves override script attributes that do not belong to the Shopware transform', () => {
        const source = stripIndent`
            <script setup lang="ts" sw-override="sw-my-component" future-flag>
            const count = 1;
            swDefineOverride({ count });
            </script>
        `;

        const result = transformOrFail(source, 'override-passthrough-attributes.vue').code;

        expect(result).toContain('<script lang="ts" future-flag>');
        expect(result).not.toContain('sw-override=');
        expect(result).not.toContain('<script setup');
    });

    it.each([
        [
            'defineModel()',
            'Vue macro defineModel() is not supported inside Shopware setup blocks.',
        ],
    ])('rejects unsupported Vue macro %s', (macro, expectedMessage) => {
        const source = stripIndent`
            <script setup sw-component="sw-my-component">
            ${macro};
            const count = 1;
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'macro.vue')).toThrow(expectedMessage);
    });

    it('ignores nested unsupported Vue macros like compiler-sfc does', () => {
        const source = stripIndent`
            <script setup sw-component="sw-my-component">
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
            <script setup sw-override="sw-my-component">
            const props = defineProps();
            swDefineOverride({});
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'override-props.vue')).toThrow(
            'defineProps() is only supported in base Shopware setup blocks.',
        );
    });

    it('rejects withDefaults() in override mode', () => {
        const source = stripIndent`
            <script setup lang="ts" sw-override="sw-my-component">
            const props = withDefaults(defineProps<{ label?: string }>(), {
                label: 'fallback',
            });
            swDefineOverride({});
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'override-props-with-defaults.vue')).toThrow(
            'withDefaults() is only supported in base Shopware setup blocks.',
        );
    });

    it('rejects defineEmits() in override mode', () => {
        const source = stripIndent`
            <script setup lang="ts" sw-override="sw-my-component">
            const emit = defineEmits(['save']);
            swDefineOverride({});
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'override-emits.vue')).toThrow(
            'defineEmits() is only supported in base Shopware setup blocks.',
        );
    });

    it('rejects defineExpose() in override mode', () => {
        const source = stripIndent`
            <script setup lang="ts" sw-override="sw-my-component">
            defineExpose({});
            swDefineOverride({});
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'override-expose.vue')).toThrow(
            'defineExpose() is only supported in base Shopware setup blocks.',
        );
    });

    it('rejects defineSlots() in override mode', () => {
        const source = stripIndent`
            <script setup lang="ts" sw-override="sw-my-component">
            const slots = defineSlots();
            swDefineOverride({});
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'override-slots.vue')).toThrow(
            'defineSlots() is only supported in base Shopware setup blocks.',
        );
    });

    it('rejects defineOptions() in override mode', () => {
        const source = stripIndent`
            <script setup lang="ts" sw-override="sw-my-component">
            defineOptions({ inheritAttrs: false });
            swDefineOverride({});
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'override-options.vue')).toThrow(
            'defineOptions() is only supported in base Shopware setup blocks.',
        );
    });

    it('rejects nested defineOptions()', () => {
        const source = stripIndent`
            <script setup sw-component="sw-my-component">
            if (true) {
                defineOptions({ inheritAttrs: false });
            }
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'nested-options.vue')).toThrow(
            'defineOptions() must be called once at the top level of a base Shopware setup block.',
        );
    });

    it('rejects nested defineExpose()', () => {
        const source = stripIndent`
            <script setup sw-component="sw-my-component">
            if (true) {
                defineExpose({});
            }
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'nested-expose.vue')).toThrow(
            'defineExpose() must be called once at the top level of a base Shopware setup block.',
        );
    });

    it('rejects top-level await', () => {
        const source = stripIndent`
            <script setup sw-override="sw-my-component">
            const value = await loadValue();
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'await.vue')).toThrow(
            'Top-level await is not supported inside Shopware setup blocks.',
        );
    });

    it('rejects unsupported script languages', () => {
        const source = stripIndent`
            <script setup lang="coffee" sw-component="sw-my-component">
            const count = 1;
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'lang.vue')).toThrow(
            'Unsupported Shopware setup script language "coffee". Supported languages are js, jsx, ts, and tsx.',
        );
    });

    it.each([
        'declare const count: number;',
        'declare function count(): number;',
        'declare class count {}',
        'declare enum count { value }',
        'declare namespace count { const value: number }',
    ])('rejects TypeScript declare declarations because they are not runtime state: %s', (declaration) => {
        const source = stripIndent`
            <script setup lang="ts" sw-component="sw-my-component">
            ${declaration}
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'declare.vue')).toThrow(
            'TypeScript declare declarations are not runtime Shopware setup bindings.',
        );
    });

    it('rejects ES module exports like native script setup', () => {
        const source = stripIndent`
            <script setup sw-component="sw-my-component">
            export const count = 1;
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'export.vue')).toThrow(
            '<script setup> cannot contain ES module exports.',
        );
    });

    it('rejects bound mode attributes', () => {
        const source = stripIndent`
            <script setup :sw-component="componentName">
            const count = 1;
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'bound.vue')).toThrow(
            'Shopware setup mode attributes must be static strings, not bound expressions.',
        );
    });

    it('rejects backslashes in setup script attributes', () => {
        const source = stripIndent`
            <script setup sw-component="sw\\my-component">
            const count = 1;
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'backslash.vue')).toThrow(
            'Backslashes are not supported in Shopware setup script attributes.',
        );
    });

    it('ignores Shopware mode attributes on non-setup script blocks', () => {
        const source = stripIndent`
            <script sw-component="sw-my-component">
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
            <script setup sw-component="sw-my-component">
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
            <script setup sw-component="sw-my-component">
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
            <script setup sw-override="sw-my-component">
            const count = 1;
            ${overrideMarker}
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'override.vue')).toThrow(expectedMessage);
    });

    it('requires swDefineOverride() in override mode', () => {
        const source = stripIndent`
            <script setup sw-override="sw-my-component">
            const count = 1;
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'missing-override.vue')).toThrow(
            'swDefineOverride() must be called exactly once at the top level of an override Shopware setup block.',
        );
    });

    it('rejects swDefineOverride() in base mode', () => {
        const source = stripIndent`
            <script setup sw-component="sw-my-component">
            const count = 1;
            swDefineOverride({ count });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'base-override.vue')).toThrow(
            'swDefineOverride() is a Shopware setup compile-time macro for override components. '
            + 'It declares which base component bindings this override replaces. '
            + 'Base components must use swDefinePublic() to expose overrideable setup bindings instead.',
        );
    });

    it('rejects imported and unknown swDefineOverride() bindings', () => {
        const source = stripIndent`
            <script setup sw-override="sw-my-component">
            import { computed } from 'vue';
            
            const count = 1;
            
            swDefineOverride({
                computed,
                missing,
            });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'override-import.vue')).toThrow(
            'Imported binding "computed" cannot be exposed with swDefineOverride().',
        );
    });

    it('ignores fake Shopware setup script tags in non-top-level contexts', () => {
        const source = stripIndent`
            <!-- <script setup sw-component="from-comment"></script> -->
            <template>
                <div data-example="<script setup sw-component='from-template'>"></div>
            </template>
            <style>
            .example::before { content: "<script setup sw-component='from-style'>"; }
            </style>
            <script setup sw-component="real-component">
            // <script setup sw-component='from-line-comment'>
            /* <script setup sw-component='from-block-comment'> */
            const single = '<script setup sw-component="from-single-string">';
            const fake = "<script setup sw-component='from-string'>";
            const template = \`<script setup sw-component="from-template-literal">\${'<script setup sw-component="from-template-expression">'}\`;
            const count = 1;
            </script>
        `;

        const result = transformOrFail(source, 'scanner.vue').code;

        expect(result).toContain("Shopware.Component.createScriptSetupExtendableComponent()('real-component'");
        expect(result).not.toContain("Shopware.Component.createScriptSetupExtendableComponent()('from-comment'");
        expect(result).not.toContain("Shopware.Component.createScriptSetupExtendableComponent()('from-template'");
        expect(result).not.toContain("Shopware.Component.createScriptSetupExtendableComponent()('from-style'");
        expect(result).not.toContain("Shopware.Component.createScriptSetupExtendableComponent()('from-line-comment'");
        expect(result).not.toContain("Shopware.Component.createScriptSetupExtendableComponent()('from-block-comment'");
        expect(result).not.toContain("Shopware.Component.createScriptSetupExtendableComponent()('from-single-string'");
        expect(result).not.toContain("Shopware.Component.createScriptSetupExtendableComponent()('from-string'");
        expect(result).not.toContain("Shopware.Component.createScriptSetupExtendableComponent()('from-template-literal'");
        expect(result).not.toContain("Shopware.Component.createScriptSetupExtendableComponent()('from-template-expression'");
    });

    it('skips transformation when Vue reports SFC parse errors', () => {
        const source = stripIndent`
            <template>
                <div>
            </template>
            <script setup sw-component="sw-my-component">
            const count = 1;
        `;

        expect(transformShopwareSetupSfc(source, 'malformed.vue')).toBeNull();
    });
});
