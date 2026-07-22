/**
 * @sw-package framework
 */

/**
 * Covers the Shopware marker macros and override runtime helpers: `swDefinePublic()` /
 * `swDefineOverride()` shape, placement, and mode rules, plus `useSwProps()` /
 * `useSwPreviousState()` rejected in base mode.
 */

import { stripIndent, transformShopwareSetupSfc } from './helpers';

describe('build/vue-setup-transform setup markers', () => {
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

    it('requires swDefineOverride() in override mode', () => {
        const source = stripIndent`
            <script setup>
            const count = 1;
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'missing-override.override.vue')).toThrow(
            'swDefineOverride() must be called exactly once at the top level of an override Shopware setup block.',
        );
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

    it('rejects imported and unknown swDefineOverride() bindings', () => {
        const source = stripIndent`
            <script setup>
            import { computed } from 'vue';

            const count = 1;

            swDefineOverride({
                computed,
                missing,
            });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'override-import.override.vue')).toThrow(
            'Imported binding "computed" cannot be exposed with swDefineOverride().',
        );
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
            <script setup>
            const count = 1;
            ${overrideMarker}
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'override.override.vue')).toThrow(expectedMessage);
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
});
