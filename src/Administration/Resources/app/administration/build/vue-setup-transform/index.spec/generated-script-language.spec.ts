/**
 * @sw-package framework
 */

import { stripIndent, transformOrFail } from './helpers';

describe('build/vue-setup-transform generated script language', () => {
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
});
