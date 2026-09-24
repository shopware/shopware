/**
 * @sw-package framework
 */

import { validateSfc } from './validate';

const filename = '/tmp/sw-validation-fixture.vue';

describe('validateSfc', () => {
    it('rejects a sibling-module binding that takes over the generated sw-block tag', () => {
        const sfc = `
            <template><sw-block name="fixture"><div /></sw-block></template>

            <script setup>
            import { swBlock } from './sw-validation-fixture.module';
            swDefinePublic({});
            </script>
        `;

        expect(validateSfc(sfc, filename, './sw-validation-fixture.module')).toBe(
            "binding 'swBlock' shadows a component tag the template renders",
        );
    });

    it('rejects a sibling-module binding that takes over a local component tag', () => {
        const sfc = `
            <template><sw-thing /></template>

            <script setup>
            import { SwThing } from './sw-validation-fixture.module';
            swDefinePublic({});
            </script>
        `;

        expect(validateSfc(sfc, filename, './sw-validation-fixture.module')).toBe(
            "binding 'SwThing' shadows a component tag the template renders",
        );
    });

    it('rejects a generated setup helper that takes over a component tag', () => {
        const sfc = `
            <template><router /></template>

            <script setup>
            import { useRouter } from 'vue-router';
            const router = useRouter();
            swDefinePublic({});
            </script>
        `;

        expect(validateSfc(sfc, filename)).toBe("binding 'router' shadows a component tag the template renders");
    });

    it('allows an authored import to provide a local component', () => {
        const sfc = `
            <template><sw-thing /></template>

            <script setup>
            import SwThing from './sw-thing.vue';
            swDefinePublic({});
            </script>
        `;

        expect(validateSfc(sfc, filename, './sw-validation-fixture.module')).toBeNull();
    });

    it('rejects a generated setup binding that takes over a directive', () => {
        const sfc = `
            <template><div v-tooltip /></template>

            <script setup>
            const vTooltip = false;
            swDefinePublic({});
            </script>
        `;

        expect(validateSfc(sfc, filename)).toBe("binding 'vTooltip' shadows a directive the template renders");
    });
});
