/**
 * @sw-package framework
 */

/**
 * Covers how the transform finds (and refuses to find) the Shopware setup block in raw SFC
 * source: filename-based mode/name inference, script-like text in attributes/comments/strings,
 * sibling script blocks, and parse-error passthrough to Vue.
 */

import { stripIndent, transformOrFail, transformShopwareSetupSfc } from './helpers';

describe('build/vue-setup-transform SFC block detection', () => {
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

        // Exactly the real block is lowered; the fake tags survive verbatim as comment/string text.
        expect(result.match(/createExtendableSetup\(/g)).toHaveLength(1);
        expect(result).toContain("const single = '<script setup>';");
        expect(result).toContain('/* <script setup> */');
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
