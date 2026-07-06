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

    it('adds an explicit generated script language when base mode had no language attribute', () => {
        const source = stripIndent`
            <script setup>
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'base-no-lang.vue').code;

        expect(result).toContain('<script setup lang="ts">');
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

    it('ignores fake Shopware setup script tags in non-top-level contexts', () => {
        const source = stripIndent`
            <!-- <script setup sw-component="from-comment"></script> -->
            <template>
                <div data-example="<script setup sw-component='from-template'>"></div>
            </template>
            <style>
            .example::before { content: "<script setup sw-component='from-style'>"; }
            </style>
            <script setup>
            // <script setup sw-component='from-line-comment'>
            /* <script setup sw-component='from-block-comment'> */
            const single = '<script setup sw-component="from-single-string">';
            const fake = "<script setup sw-component='from-string'>";
            const template = \`<script setup sw-component="from-template-literal">\${'<script setup sw-component="from-template-expression">'}\`;
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'scanner.vue').code;

        expect(result).toContain('Shopware.Component.createExtendableSetup(');
        expect(result).toContain("name: 'scanner'");
        expect(result).not.toContain("name: 'from-comment'");
        expect(result).not.toContain("name: 'from-template'");
        expect(result).not.toContain("name: 'from-style'");
        expect(result).not.toContain("name: 'from-line-comment'");
        expect(result).not.toContain("name: 'from-block-comment'");
        expect(result).not.toContain("name: 'from-single-string'");
        expect(result).not.toContain("name: 'from-string'");
        expect(result).not.toContain("name: 'from-template-literal'");
        expect(result).not.toContain("name: 'from-template-expression'");
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
