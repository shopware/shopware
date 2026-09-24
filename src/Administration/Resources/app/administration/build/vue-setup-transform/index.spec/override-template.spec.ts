/**
 * @sw-package framework
 *
 * Covers how override-local bindings reach `<sw-block extends>` content: every one of them is forwarded
 * through the generated slot scope, and Vue's own scoping decides what the content reads. Rejections
 * live in `override-template-guards.spec.ts`.
 */

import { expectVueCompilerScriptToCompile, stripIndent, stripWhitespace, transformOrFail } from './helpers';

describe('build/vue-setup-transform override template forwarding', () => {
    it('forwards every override local, public ones by name and the rest under the namespace', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <p>{{ body }}</p>
            </sw-block>
            </template>
            <script setup lang="ts">
            import { computed, ref } from 'vue';

            const previousState = useSwPreviousState();
            const info = ref('local');
            const unused = ref('not read by the template');
            const body = computed(() => previousState.body.value + info.value);

            swDefineOverride({
                body,
            });
            </script>
        `;

        const result = transformOrFail(source, 'src/plugin/sw-example-component.override.vue').code;

        expect(result).toContain(
            '<sw-block extends="sw_example_component_body" #default="{ __swOverride: { [__swSetupNamespace]: { info, unused, previousState } = {} } = {}, body }">',
        );
        expect(stripWhitespace(result)).toContain(stripWhitespace`
            return {
                body,
                __swOverride: {
                    [__swSetupNamespace]: {
                        info,
                        unused,
                        previousState,
                    },
                },
            };
        `);
        expectVueCompilerScriptToCompile(result, 'sw-example-component.override.vue');
    });

    it('keeps the slot scope destructurable for hosts without override-local state', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <p>{{ info }}</p>
            </sw-block>
            </template>
            <script setup>
            const info = 'local';
            const headline = 'public';

            swDefineOverride({ headline });
            </script>
        `;

        const pattern = /#default="([^"]+)"/.exec(transformOrFail(source, 'defaults.override.vue').code)?.[1];
        // eslint-disable-next-line @typescript-eslint/no-implied-eval
        const createSlot = new Function('__swSetupNamespace', `return (${pattern}) => [info, headline];`) as (
            namespace: symbol,
        ) => (scope: object) => unknown[];
        const slot = createSlot(Symbol('ns'));

        // Options API hosts and nested Twig blocks pass no `__swOverride` entry at all.
        expect(slot({ headline: 'host' })).toEqual([undefined, 'host']);
        expect(slot({ __swOverride: {} })).toEqual([undefined, undefined]);
    });

    it('forwards every useSw* alias, since the content may read any of them', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <p>{{ props.title }}</p>
            </sw-block>
            </template>
            <script setup>
            const props = useSwProps();
            const context = useSwContext();

            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'usesw-props-forward.override.vue').code;

        expect(result).toContain('#default="{ __swOverride: { [__swSetupNamespace]: { props, context } = {} } = {} }"');
    });

    it('forwards a binding read through the same-name shorthand :item-count', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <sw-counter :item-count />
            </sw-block>
            </template>
            <script setup>
            const itemCount = 3;

            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'same-name-shorthand.override.vue').code;

        // `:item-count` binds `itemCount`, not `item - count`.
        expect(result).toContain('#default="{ __swOverride: { [__swSetupNamespace]: { itemCount } = {} } = {} }"');
        expect(result).toContain('itemCount,');
    });

    it('destructures only the public names when every local is public', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_headline">
                <h2>{{ headline }}</h2>
            </sw-block>
            </template>
            <script setup>
            const headline = 'Headline';

            swDefineOverride({
                headline,
            });
            </script>
        `;

        const result = transformOrFail(source, 'public-only.override.vue').code;

        expect(result).toContain('<sw-block extends="sw_example_component_headline" #default="{ headline }">');
        expect(result).not.toContain('__swOverride');
        expect(result).not.toContain('__swSetupNamespace');
        expect(result).not.toContain(':data="$dataScope"');
    });

    it('adds no slot scope to a block when the override has no locals', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <p>static</p>
            </sw-block>
            </template>
            <script setup>
            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'no-locals.override.vue').code;

        expect(result).toContain('<sw-block extends="sw_example_component_body">');
        expect(result).toContain('return {};');
    });

    it('forwards nothing through the namespace when the override has no <sw-block extends>', () => {
        const source = stripIndent`
            <script setup>
            const local = 1;
            const headline = local + 1;

            swDefineOverride({ headline });
            </script>
        `;

        const result = transformOrFail(source, 'script-only.override.vue').code;

        expect(result).not.toContain('__swSetupNamespace');
        expect(stripWhitespace(result)).toContain(stripWhitespace`
            return {
                headline,
            };
        `);
    });

    it.each([
        '#default="{ body }"',
        '#default="slotProps"',
        '#default',
        'v-slot="{ body }"',
    ])('rejects the authored default slot scope %s on sw-block', (slotBinding) => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body" ${slotBinding}>
                <p>{{ info }}</p>
            </sw-block>
            </template>
            <script setup>
            const info = 'local';

            swDefineOverride({});
            </script>
        `;

        expect(() => transformOrFail(source, 'authored-slot-scope.override.vue')).toThrow(
            'The default slot scope of <sw-block> is generated by the Shopware setup transform and must not be authored.',
        );
    });

    it('emits the extended block names for the ownership cross-check', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_headline">
                <h2>headline</h2>
            </sw-block>
            <sw-block extends="sw_example_component_body">
                <p>body</p>
            </sw-block>
            </template>
            <script setup>
            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'extended-names.override.vue');

        expect(result.extendedBlockNames).toEqual(['sw_example_component_headline', 'sw_example_component_body']);
        expect(result.ownedBlockNames).toEqual([]);
    });
});
