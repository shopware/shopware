/**
 * @sw-package framework
 */

/**
 * Covers the Shopware marker macros and override runtime helpers: `swDefinePublic()` /
 * `swDefineOverride()` shape, placement, and mode rules, plus `useSwProps()` /
 * `useSwPreviousState()` rejected in base mode.
 */

import { stripIndent, transformOrFail, transformShopwareSetupSfc } from './helpers';

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
            // A nested call is not rejected on its own, matching how Vue treats its own macros: it only
            // recognises them at the top level. The marker is simply missing where it counts, so the
            // required-marker rule reports it.
            'if (true) { swDefinePublic({ count }); }',
            'A base Shopware setup component must declare its extension surface.',
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

    it('requires swDefinePublic() in base mode', () => {
        const source = stripIndent`
            <script setup>
            const count = 1;
            </script>
        `;

        // A transformed base component is an extension point - its filename is the public override
        // target - so the marker is mandatory even with nothing public, rather than letting a file become
        // extendable just by carrying a <script setup> block.
        expect(() => transformShopwareSetupSfc(source, 'missing-public.vue')).toThrow(
            'A base Shopware setup component must declare its extension surface. Add swDefinePublic({ ... }) ' +
                'at the top level - pass an empty object if no binding is public.',
        );
    });

    it.each([
        [
            'public.vue',
            'swDefinePublic({ count });\nswDefinePublic({});',
            'Only one swDefinePublic() call is allowed in a base Shopware setup block.',
        ],
        [
            'override.override.vue',
            'swDefineOverride({ count });\nswDefineOverride({});',
            'Only one swDefineOverride() call is allowed in an override Shopware setup block.',
        ],
    ])('rejects a second top-level marker call in %s', (filename, markers, expectedMessage) => {
        const source = stripIndent`
            <script setup>
            const count = 1;
            ${markers}
            </script>
        `;

        // Only the first marker is carried downstream, so this is the one check that keeps a duplicate
        // from being silently ignored - it counts off the full macro-entry list rather than that field.
        expect(() => transformShopwareSetupSfc(source, filename)).toThrow(expectedMessage);
    });

    it.each([
        [
            'public.vue',
            'const marker = swDefinePublic({ count });',
            'swDefinePublic() is a compile-time marker and returns nothing.',
        ],
        [
            'override.override.vue',
            'const marker = swDefineOverride({ count });',
            'swDefineOverride() is a compile-time marker and returns nothing.',
        ],
    ])('rejects a marker assigned to a variable in %s', (filename, marker, expectedMessage) => {
        const source = stripIndent`
            <script setup>
            const count = 1;
            ${marker}
            </script>
        `;

        // The marker statement is removed from the output, so a declaration form would leave the call
        // behind as a reference to a name that does not exist at runtime - and its entries would be
        // silently ignored, because only the statement form is read.
        expect(() => transformShopwareSetupSfc(source, filename)).toThrow(expectedMessage);
    });

    it('accepts an empty swDefinePublic() for a base component with no public state', () => {
        const source = stripIndent`
            <script setup>
            const internalOnly = 1;

            swDefinePublic({});
            </script>
        `;

        const result = transformOrFail(source, 'empty-public.vue').code;

        expect(result).toContain('public: {},');
        expect(result).toContain('internalOnly: __swSetupAuthor_internalOnly,');
    });

    it('rejects swDefineOverride() in base mode', () => {
        const source = stripIndent`
            <script setup>
            const count = 1;
            swDefineOverride({ count });
            </script>
        `;

        // The wrong-mode complaint must win over the missing-swDefinePublic() one: both are true here,
        // but only this one explains what the author actually did wrong.
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
            // As above: a nested call is left alone, and the required-marker rule reports the absence.
            'if (true) { swDefineOverride({ count }); }',
            'swDefineOverride() must be called exactly once at the top level',
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
