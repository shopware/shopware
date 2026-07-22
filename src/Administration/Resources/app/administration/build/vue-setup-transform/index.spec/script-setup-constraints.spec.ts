/**
 * @sw-package framework
 */

/**
 * Covers constraints on the `<script setup>` body itself, independent of any specific macro:
 * top-level await, ES module exports, the reserved `__swSetup` binding prefix, and ambient
 * `declare` hoisting.
 */

import { stripIndent, transformOrFail, transformShopwareSetupSfc } from './helpers';

describe('build/vue-setup-transform script setup constraints', () => {
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

    it('allows importing Vue macros from vue while keeping them macros', () => {
        const source = stripIndent`
            <script setup>
            import { defineProps } from 'vue';
            const props = defineProps({ count: Number });
            const doubled = props.count * 2;
            swDefinePublic({ doubled });
            </script>
        `;

        const result = transformOrFail(source, 'vue-macro-import.vue').code;

        // The import is legal (Vue itself drops it again during compilation) and the call is still
        // hoisted as the props macro.
        expect(result).toContain("import { defineProps } from 'vue';");
        expect(result).toContain('const __swSetupPropsDeclaration = defineProps({ count: Number });');
    });

    it('rejects importing a Vue macro name from anywhere but vue', () => {
        const source = stripIndent`
            <script setup>
            import { defineProps } from './my-utils';
            const props = defineProps({ count: Number });
            swDefinePublic({ props });
            </script>
        `;

        // Vue would still treat the calls as macros (macros match by name and never yield to an
        // import), silently hijacking the imported function - so the import is rejected outright.
        expect(() => transformShopwareSetupSfc(source, 'macro-name-import.vue')).toThrow(
            '"defineProps" is reserved by the Shopware setup transform and must not be declared or imported.',
        );
    });

    it('still rejects declaring a Vue macro name locally', () => {
        const source = stripIndent`
            <script setup>
            const defineProps = () => ({});
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'declared-macro-name.vue')).toThrow(
            '"defineProps" is reserved by the Shopware setup transform and must not be declared or imported.',
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
});
