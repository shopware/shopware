/**
 * @sw-package framework
 */

import { stripIndent, transformOrFail } from './helpers';

describe('build/vue-setup-transform script attributes', () => {
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

});
