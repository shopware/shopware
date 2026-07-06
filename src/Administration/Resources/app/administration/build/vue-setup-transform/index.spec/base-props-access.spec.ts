/**
 * @sw-package framework
 */

import { stripIndent, transformOrFail } from './helpers';

describe('build/vue-setup-transform base props access', () => {
    it('rewrites props access by source ranges instead of placeholder string replacement', () => {
        const source = stripIndent`
            <script setup>
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
        expect(result).toContain('const props = (__swSetupProps);');
    });
});
