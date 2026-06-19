/**
 * @sw-package framework
 */

import { stripIndent, transformOrFail } from './helpers';

describe('build/vue-setup-transform base transforms', () => {
    it('transforms base Shopware setup blocks with auto-private state and explicit public state', () => {
        const source = stripIndent`
            <template><div>{{ count }}{{ foo2 }}</div></template>
            <script setup lang="ts" sw-component="sw-my-component">
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
            
            const props = defineProps<{
                initialCount?: number;
            }>();

            const {
                count,
                doubled,
                internalThing,
                foo2,
                __swOverride,
            } = Shopware.Component.createExtendableSetup(
                {
                    name: 'sw-my-component',
                    props: props,
                },
                (__shopwareProps, __shopwareContext) => {
                    const useSwContext = () => __shopwareContext;
            
                    const props = (__shopwareProps);
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
                            internalThing,
                        },
                    };
                },
            );
            </script>
        `;

        expect(transformOrFail(source, 'base.vue').code).toBe(expected);
    });

    it('adds the generated data scope to base sw-block declarations', () => {
        const source = stripIndent`
            <template>
            <article>
                <sw-block name="sw_example_component_headline">
                    <h2>{{ headline }}</h2>
                </sw-block>
            
                <sw-block :name="dynamicBlockName">
                    <p>{{ headline }}</p>
                </sw-block>
            </article>
            </template>
            <script setup sw-component="sw-my-component">
            const dynamicBlockName = 'sw_example_component_dynamic';
            const headline = 'Headline';
            
            swDefinePublic({
                headline,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-sw-block-data.vue').code;

        expect(result).toContain('<sw-block :data="$dataScope" name="sw_example_component_headline">');
        expect(result).toContain('<sw-block :data="$dataScope" :name="dynamicBlockName">');
    });

    it('returns destructured runtime declarations as setup bindings', () => {
        const source = stripIndent`
            <template>
            <p>{{ publicTitle }}{{ localLabel }}{{ firstItem }}{{ rest.enabled }}</p>
            </template>
            <script setup sw-component="sw-my-component">
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
        expect(result).toContain('private: {\n                source,\n                items,\n                fallbackLabel,\n                localLabel,\n                rest,\n                firstItem,\n            }');
    });

    it('adds the generated data scope before object v-bind so the user can override it', () => {
        const source = stripIndent`
            <template>
            <sw-block
                v-bind="blockProps"
                name="bulk_bound"
            >
                <span>{{ headline }}</span>
            </sw-block>
            </template>
            <script setup sw-component="sw-my-component">
            const headline = 'Headline';
            const blockProps = {};
            
            swDefinePublic({
                headline,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-sw-block-bulk-v-bind.vue').code;

        expect(result).toContain(`<sw-block :data="$dataScope"
    v-bind="blockProps"
    name="bulk_bound"`);
    });

    it('does not duplicate explicit user-provided data on base sw-block declarations', () => {
        const source = stripIndent`
            <template>
            <article>
                <sw-block name="static_data" data="legacy">
                    <span>{{ headline }}</span>
                </sw-block>
            
                <sw-block name="bound_data" :data="customData">
                    <span>{{ headline }}</span>
                </sw-block>
            
                <sw-block name="longhand_bound_data" v-bind:data="customData">
                    <span>{{ headline }}</span>
                </sw-block>
            </article>
            </template>
            <script setup sw-component="sw-my-component">
            const headline = 'Headline';
            const customData = {};
            
            swDefinePublic({
                headline,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-sw-block-existing-data.vue').code;

        expect(result).toContain('<sw-block name="static_data" data="legacy">');
        expect(result).toContain('<sw-block name="bound_data" :data="customData">');
        expect(result).toContain('<sw-block name="longhand_bound_data" v-bind:data="customData">');
        expect(result).not.toContain('data="legacy" :data="$dataScope"');
        expect(result).not.toContain(':data="customData" :data="$dataScope"');
        expect(result).not.toContain('v-bind:data="customData" :data="$dataScope"');
    });

    it('adds the generated data scope to nested and self-closing base sw-block declarations', () => {
        const source = stripIndent`
            <template>
            <sw-block name="outer">
                <sw-block name="inner" />
            </sw-block>
            </template>
            <script setup sw-component="sw-my-component">
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
});
