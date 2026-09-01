/**
 * @sw-package framework
 */

/**
 * Covers constraints on the `<script setup>` body itself, independent of any specific macro:
 * top-level await, ES module exports, the reserved `__swSetup` binding prefix, and ambient
 * `declare` hoisting.
 */

import { expectVueCompilerScriptToCompile, stripIndent, transformOrFail, transformShopwareSetupSfc } from './helpers';

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

        // The import is legal (Vue itself drops it again during compilation) and the call stays a props
        // macro in place; only the binding is renamed to its author alias.
        expect(result).toContain("import { defineProps } from 'vue';");
        expect(result).toContain('const __swSetupAuthor_props = defineProps({ count: Number });');
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

    it('rejects a `__proto__` binding that the generated state map would silently drop', () => {
        const source = stripIndent`
            <script setup>
            const __proto__ = 7;
            swDefinePublic({ __proto__ });
            </script>
        `;

        // `__proto__: alias` is prototype-setter syntax, not an own key, so the footer would read the
        // prototype instead of the value. Reject rather than silently corrupt.
        expect(() => transformShopwareSetupSfc(source, 'proto.vue')).toThrow(
            '"__proto__" cannot be a Shopware setup binding',
        );
    });

    it('keeps ambient declare declarations in place without collecting them as state', () => {
        const source = stripIndent`
            <script setup lang="ts">
            declare const injected: number;
            const count = injected + 1;
            swDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'declare.vue').code;

        // Ambient declarations describe runtime values provided from elsewhere: they are not runtime
        // bindings, so they are neither renamed nor collected as returned setup state.
        expect(result).toContain('declare const injected: number;');
        expect(result.indexOf('declare const injected')).toBeLessThan(result.indexOf('attachOverrides('));
        expect(result).toContain('const __swSetupAuthor_count = injected + 1;');
        expect(result).not.toMatch(/\n\s*injected,/);
    });

    it('accepts type-only exports and leaves them in place', () => {
        const source = stripIndent`
            <script setup lang="ts">
            export type PublicCount = number;
            export interface PublicShape {
                count: number;
            }
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'type-only-export.vue').code;

        expect(result).toContain('export type PublicCount = number;');
        // The interface member key is a type-space name and must survive the runtime rename untouched.
        expect(result).toContain('count: number;');
        expect(result.indexOf('export interface PublicShape')).toBeLessThan(
            result.indexOf('Shopware.Component.attachOverrides('),
        );
        expectVueCompilerScriptToCompile(result, 'type-only-export.vue');
    });

    it('accepts exports inside an ambient module augmentation', () => {
        const source = stripIndent`
            <script setup lang="ts">
            declare module 'vue' {
                export interface ComponentCustomProperties {
                    $foo: string;
                }
            }
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'ambient-module-export.vue').code;

        expect(result).toContain("declare module 'vue' {");
        expect(result.indexOf("declare module 'vue'")).toBeLessThan(result.indexOf('Shopware.Component.attachOverrides('));
    });

    it('still rejects a value-carrying named export', () => {
        const source = stripIndent`
            <script setup lang="ts">
            export const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'value-export.vue')).toThrow(
            '<script setup> cannot contain ES module exports.',
        );
    });

    it('rejects a top-level binding named Shopware that would shadow the runtime global', () => {
        const source = stripIndent`
            <script setup>
            const Shopware = { custom: true };
            const count = Shopware.custom;
            swDefinePublic({ count });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'reserved-shopware.vue')).toThrow(
            '"Shopware" is reserved by the Shopware setup transform',
        );
    });

    it('rejects an unsupported script lang', () => {
        const source = stripIndent`
            <script setup lang="coffee">
            count = 1
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'coffee.vue')).toThrow(
            'Unsupported <script setup lang="coffee"> in a Shopware setup block.',
        );
    });

    it('accepts a tsx script lang', () => {
        const source = stripIndent`
            <script setup lang="tsx">
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'tsx-lang.vue').code;

        expect(result).toContain('Shopware.Component.attachOverrides(');
    });

    it('reports transform errors at the offending author source offset', () => {
        const source = stripIndent`
            <template><div /></template>
            <script setup>
            const value = await loadValue();
            swDefinePublic({});
            </script>
        `;

        let thrown: unknown;

        try {
            transformShopwareSetupSfc(source, 'offset.vue');
        } catch (error) {
            thrown = error;
        }

        const error = thrown as { name: string; index: number; endIndex: number };

        // The absolute SFC offset must land on the offending `await`, not at 0 (block-relative) or the
        // block start - this is the contract index.ts's withBlockOffset() exists to preserve.
        expect(error.name).toBe('ShopwareSetupTransformError');
        expect(source.slice(error.index, error.index + 'await'.length)).toBe('await');
        // The error also carries the full node range (endIndex), so ESLint can underline the whole
        // offending expression rather than a single point.
        expect(source.slice(error.index, error.endIndex)).toBe('await loadValue()');
    });

    it('carries the full binding range on a reserved-name error so the whole name is underlined', () => {
        const source = stripIndent`
            <template><div /></template>
            <script setup>
            const Shopware = { custom: true };
            swDefinePublic({});
            </script>
        `;

        let thrown: unknown;

        try {
            transformShopwareSetupSfc(source, 'reserved-range.vue');
        } catch (error) {
            thrown = error;
        }

        const error = thrown as { name: string; index: number; endIndex: number };

        expect(error.name).toBe('ShopwareSetupTransformError');
        // The range spans the offending binding identifier, not just its first character.
        expect(source.slice(error.index, error.endIndex)).toBe('Shopware');
    });
});
