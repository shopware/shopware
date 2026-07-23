/**
 * @sw-package framework
 */

import { stripIndent, transformOrFail, transformShopwareSetupSfc } from './helpers';

describe('build/vue-setup-transform base transforms', () => {
    it('transforms base Shopware setup blocks with auto-private state and explicit public state', () => {
        const source = stripIndent`
            <template><div>{{ count }}{{ foo2 }}</div></template>
            <script setup lang="ts">
            import { ref, computed } from 'vue';

            const props = defineProps<{
                initialCount?: number;
            }>();
            const count = ref(props.initialCount ?? 0);
            const doubled = computed(() => count.value * 2);
            const internalThing = ref('secret');
            const foo2 = ref('bar');

            swDefinePublic({
                count,
                doubled,
                foo2
            });
            </script>
        `;

        const expected = stripIndent`
            <template><div>{{ count }}{{ foo2 }}</div></template>
            <script setup lang="ts">
            import { ref, computed } from 'vue';

            const __swSetupPropsDeclaration = defineProps<{
                initialCount?: number;
            }>();

            const {
                props,
                count,
                doubled,
                internalThing,
                foo2,
                __swOverride,
            } = Shopware.Component.createExtendableSetup(
                {
                    name: 'sw-my-component',
                    props: __swSetupPropsDeclaration,
                },
                (__swSetupProps, __swSetupContext) => {
                    const useSwContext = () => __swSetupContext;

                    const props = (__swSetupProps);
                    const count = ref(props.initialCount ?? 0);
                    const doubled = computed(() => count.value * 2);
                    const internalThing = ref('secret');
                    const foo2 = ref('bar');

                    return {
                        public: {
                            count,
                            doubled,
                            foo2,
                        },
                        private: {
                            props,
                            internalThing,
                        },
                    };
                },
            );
            </script>
        `;

        expect(transformOrFail(source, 'sw-my-component.vue').code).toBe(expected);
    });

    it('preserves multi-line template literal contents instead of re-indenting them', () => {
        const source = stripIndent`
            <script setup>
            const message = \`hello
            world\`;
            const count = 1;

            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-template-literal.vue').code;

        // The callback body is indented by 8 spaces, but the interior line of the template literal must
        // keep its original column - indenting it would add spaces to the runtime string.
        expect(result).toContain('const message = `hello\nworld`;');
        expect(result).not.toContain('hello\n        world');
    });

    it('supports import-only script setup blocks with empty state', () => {
        const source = stripIndent`
            <template><ChildComponent /></template>
            <script setup>
            import ChildComponent from './child.vue';
            </script>
        `;

        const result = transformOrFail(source, 'import-only.vue').code;

        expect(result).toContain("import ChildComponent from './child.vue';");
        expect(result).toContain('public: {},');
        expect(result).toContain('private: {},');
    });

    it('supports macro-only script setup blocks with empty state', () => {
        const source = stripIndent`
            <template><button @click="$emit('save')">save</button></template>
            <script setup>
            defineOptions({ inheritAttrs: false });
            defineEmits(['save']);
            </script>
        `;

        const result = transformOrFail(source, 'macro-only.vue').code;

        expect(result).toContain('defineOptions({ inheritAttrs: false });');
        expect(result).toContain("defineEmits(['save']);");
        expect(result).toContain('public: {},');
        expect(result).toContain('private: {},');
    });

    it('adds the generated data scope to base sw-block declarations', () => {
        const source = stripIndent`
            <template>
            <article>
                <sw-block name="sw_example_component_headline">
                    <h2>{{ headline }}</h2>
                </sw-block>
            </article>
            </template>
            <script setup>
            const headline = 'Headline';

            swDefinePublic({
                headline,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-sw-block-data.vue').code;

        expect(result).toContain('<sw-block :data="$dataScope" name="sw_example_component_headline">');
    });

    it('returns destructured runtime declarations as setup bindings', () => {
        const source = stripIndent`
            <template>
            <p>{{ publicTitle }}{{ localLabel }}{{ firstItem }}{{ rest.enabled }}</p>
            </template>
            <script setup>
            const source = {
                title: 'Title',
                nested: {
                    label: 'Label',
                },
                enabled: true,
            };
            const items = ['first'];
            const fallbackLabel = 'Fallback';

            const {
                title: publicTitle,
                nested: {
                    label: localLabel = fallbackLabel,
                },
                ...rest
            } = source;
            const [firstItem] = items;

            swDefinePublic({
                publicTitle,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-destructured-runtime.vue').code;

        expect(result).toContain('publicTitle,');
        expect(result).toContain(
            'private: {\n                source,\n                items,\n                fallbackLabel,\n                localLabel,\n                rest,\n                firstItem,\n            }',
        );
    });

    it('adds the generated data scope to nested and self-closing base sw-block declarations', () => {
        const source = stripIndent`
            <template>
            <sw-block name="outer">
                <sw-block name="inner" />
            </sw-block>
            </template>
            <script setup>
            const count = 1;

            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-nested-sw-block-data.vue').code;

        expect(result).toContain('<sw-block :data="$dataScope" name="outer">');
        expect(result).toContain('<sw-block :data="$dataScope" name="inner" />');
    });

    it.each([
        'data="scope"',
        ':data="scope"',
        'v-bind:data="scope"',
    ])('rejects the authored data binding %s on base sw-block declarations', (dataBinding) => {
        const source = stripIndent`
            <template>
            <sw-block name="sw_example_component_body" ${dataBinding}>
                <p>{{ body }}</p>
            </sw-block>
            </template>
            <script setup>
            const scope = {};
            const body = 'Body';

            swDefinePublic({
                body,
            });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'sw-authored-data.vue')).toThrow(
            'The data binding of <sw-block> is generated by the Shopware setup transform and must not be authored.',
        );
    });

    it('rejects a <sw-block extends> in a base component', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <p>{{ body }}</p>
            </sw-block>
            </template>
            <script setup>
            const body = 'Body';

            swDefinePublic({
                body,
            });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'base-extends.vue')).toThrow(
            '<sw-block extends="..."> is only valid in an override component.',
        );
    });

    it('emits the owned base block names for the ownership registry', () => {
        const source = stripIndent`
            <template>
            <sw-block name="sw_outer">
                <sw-block name="sw_inner" />
            </sw-block>
            </template>
            <script setup>
            const count = 1;

            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-owned-blocks.vue');

        expect(result.ownedBlockNames).toEqual([
            'sw_outer',
            'sw_inner',
        ]);
    });

    it('rejects v-bind objects on base sw-block declarations', () => {
        const source = stripIndent`
            <template>
            <sw-block name="sw_example_component_body" v-bind="attrs">
                <p>{{ body }}</p>
            </sw-block>
            </template>
            <script setup>
            const attrs = {};
            const body = 'Body';

            swDefinePublic({
                body,
            });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'sw-authored-v-bind.vue')).toThrow(
            'v-bind objects are not supported on <sw-block>, because they could carry the generated data or slot bindings.',
        );
    });
});
