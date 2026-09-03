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

        expect(result.code).toContain('Shopware.Component.attachOverrides(');
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

        expect(result).toContain('Shopware.Component.attachOverrides(');
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

        // Exactly the real block is lowered; the fake tags survive verbatim as comment/string text
        // (the binding is renamed, but the string literal content is untouched).
        expect(result.match(/attachOverrides\(/g)).toHaveLength(1);
        expect(result).toContain("= '<script setup>';");
        expect(result).toContain('/* <script setup> */');
    });

    it.each([
        [
            'an Options API script block',
            stripIndent`
                <script>
                export default { name: 'sw-thing' };
                </script>
            `,
        ],
        [
            'a template-only SFC',
            stripIndent`
                <template><div>no script at all</div></template>
            `,
        ],
    ])('rejects %s, which could never be extended', (_name, source) => {
        // Every `.vue` component in the Administration is extendable, and the extension surface is
        // declared by markers that only exist inside `<script setup>`. An SFC without that block would
        // compile into a component nothing can override, so it is refused rather than passed through.
        expect(() => transformShopwareSetupSfc(source, 'sw-thing.vue')).toThrow(
            'A Shopware setup component needs a <script setup> block.',
        );
    });

    it('takes the component name from the directory for an index file', () => {
        const source = stripIndent`
            <script setup>
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        // `sw-thing/index.vue` is a documented form, so the directory name is what gets validated and
        // registered - `index` itself would never pass the name rule.
        expect(transformOrFail(source, 'sw-thing/index.vue').code).toContain("name: 'sw-thing'");
    });

    it.each([
        [
            'a template-only override',
            stripIndent`
                <template><div>nothing registers me</div></template>
            `,
        ],
        [
            'an override with a non-setup script block',
            stripIndent`
                <template><div /></template>
                <script>
                export default { name: 'sw-thing' };
                </script>
            `,
        ],
    ])('rejects %s, which would silently register nothing', (_name, source) => {
        // The `.override.vue` filename declares the intent, and an override registers itself from its
        // `<script setup>` body - so without that block the file is transformed away to nothing and the
        // override never applies. Returning null here (as for a plain `.vue`) would be a silent no-op.
        expect(() => transformShopwareSetupSfc(source, 'sw-thing.override.vue')).toThrow(
            'An override component needs a <script setup> block to register its override.',
        );
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

    it('preserves the codemod module prelude marker beside setup code', () => {
        const source = stripIndent`
            <script data-sfc-migration-module>
            export const moduleValue = 1;
            </script>
            <script setup>
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'marked-module.vue');

        expect(result.code).toContain('<script data-sfc-migration-module>');
        expect(result.code).toContain('export const moduleValue = 1;');
        expect(result.code).toContain("name: 'marked-module'");
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
