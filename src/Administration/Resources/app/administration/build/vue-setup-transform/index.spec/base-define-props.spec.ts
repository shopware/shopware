/**
 * @sw-package framework
 */

import { expectVueCompilerScriptToCompile, stripIndent, transformOrFail, transformShopwareSetupSfc } from './helpers';

describe('build/vue-setup-transform base defineProps macro', () => {
    it('keeps base defineProps() in place and passes the props object into attachOverrides', () => {
        const source = stripIndent`
            <template><div>{{ count }}</div></template>
            <script setup lang="ts">
            import { ref } from 'vue';

            const props = defineProps<{
                initialCount?: number;
            }>();
            const count = ref(props.initialCount ?? 0);

            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'sw-my-component.vue').code;

        expect(result).toContain(`const __swSetupAuthor_props = defineProps<{
    initialCount?: number;
}>();`);
        expect(result).toContain('Shopware.Component.attachOverrides(');
        expect(result).toContain("name: 'sw-my-component'");
        expect(result).toContain('props: __swSetupAuthor_props,');
        expect(result).toContain('const __swSetupAuthor_count = ref(__swSetupAuthor_props.initialCount ?? 0);');
    });

    it('keeps a local prop type declaration in place for defineProps()', () => {
        const source = stripIndent`
            <script setup lang="ts">
            interface Props {
                initialCount?: number;
            }

            const props = defineProps<Props>();
            const count = props.initialCount ?? 0;

            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-props-local-type.vue').code;

        expect(result.indexOf('interface Props')).toBeLessThan(
            result.indexOf('const __swSetupAuthor_props = defineProps<Props>()'),
        );
        expectVueCompilerScriptToCompile(result, 'base-props-local-type.vue');
    });

    it('rejects local setup bindings in defineProps() arguments', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const defaultCount = 1;
            const props = defineProps({
                initialCount: {
                    default: defaultCount,
                },
            });
            const count = props.initialCount;

            swDefinePublic({
                count,
            });
            </script>
        `;

        // Vue hoists defineProps() to the component options object, so referencing a local setup
        // binding in its argument is invalid — we reject it with an actionable message up front.
        expect(() => transformShopwareSetupSfc(source, 'base-props-local-runtime-value.vue')).toThrow(
            'defineProps() arguments are hoisted outside the Shopware setup callback and must not reference local setup bindings.',
        );
    });

    it('allows macro-local function parameters to shadow setup bindings', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const defaultCount = 1;
            const props = defineProps({
                initialCount: {
                    validator: (defaultCount: number) => defaultCount > 0,
                },
            });
            const count = props.initialCount ?? defaultCount;

            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-props-shadowed-runtime-value.vue').code;

        // The validator parameter shadows the top-level `defaultCount`, so it stays untouched while the
        // outer binding is renamed.
        expect(result).toContain('validator: (defaultCount: number) => defaultCount > 0');
        expect(result).toContain(
            'const __swSetupAuthor_count = __swSetupAuthor_props.initialCount ?? __swSetupAuthor_defaultCount;',
        );
    });

    it('allows a macro-argument body-local binding that shadows a top-level setup binding', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const count = 1;
            const props = defineProps({
                initial: {
                    default: () => {
                        const count = 2;
                        return count;
                    },
                },
            });
            const total = props.initial + count;

            swDefinePublic({
                total,
            });
            </script>
        `;

        // The inner `const count` shadows the top-level `count` for the whole factory body, so it is
        // neither flagged as a local-setup reference nor renamed with the outer binding.
        const result = transformOrFail(source, 'base-props-body-local-shadow.vue').code;

        expect(result).toContain('const count = 2;');
        expect(result).toContain('const __swSetupAuthor_total = __swSetupAuthor_props.initial + __swSetupAuthor_count;');
    });

    it('supports destructured defineProps() by leaving it for Vue 3.5 reactive-props-destructure', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const { initialCount = 0 } = defineProps<{
                initialCount?: number;
            }>();
            const doubled = computed(() => initialCount * 2);

            swDefinePublic({
                doubled,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-destructured-props.vue').code;

        // The destructure is left untouched (names not aliased, not in the footer) so Vue's compiler
        // rewrites `initialCount` into a reactive `props.initialCount` read - including inside the
        // renamed `doubled` computed.
        expect(result).toContain('const { initialCount = 0 } = defineProps<{');
        expect(result).toContain('const __swSetupAuthor_doubled = computed(() => initialCount * 2);');
        expect(result).not.toContain('__swSetupAuthor_initialCount');
        // Vue's own compiler accepts the output and applies the reactive-props-destructure rewrite.
        expectVueCompilerScriptToCompile(result, 'base-destructured-props.vue');
    });

    it('supports a bare destructured defineProps()', () => {
        const source = stripIndent`
            <script setup>
            const { initialCount = 0 } = defineProps();
            const doubled = computed(() => initialCount * 2);

            swDefinePublic({
                doubled,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-destructured-bare-props.vue').code;

        expect(result).toContain('const { initialCount = 0 } = defineProps();');
        expect(result).not.toContain('__swSetupAuthor_initialCount');
    });

    it('keeps a bare defineProps() statement in place', () => {
        const source = stripIndent`
            <script setup>
            const props = defineProps();

            const count = 1;

            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-bare-props.vue').code;

        expect(result).toContain('const __swSetupAuthor_props = defineProps();');
        expect(result).toContain('props: __swSetupAuthor_props,');
    });

    it('keeps base withDefaults(defineProps()) in place', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const props = withDefaults(defineProps<{
                initialCount?: number;
                labels?: string[];
            }>(), {
                initialCount: 3,
                labels: () => ['main'],
            });
            const count = props.initialCount;

            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'sw-my-component.vue').code;

        expect(result).toContain(`const __swSetupAuthor_props = withDefaults(defineProps<{
    initialCount?: number;
    labels?: string[];
}>(), {
    initialCount: 3,
    labels: () => ['main'],
});`);
        expect(result).toContain("name: 'sw-my-component'");
        expect(result).toContain('props: __swSetupAuthor_props,');
        expect(result).toContain('const __swSetupAuthor_count = __swSetupAuthor_props.initialCount;');
        expect(result.match(/defineProps/g)).toHaveLength(1);
        expect(result.match(/withDefaults/g)).toHaveLength(1);
    });

    it('rejects local setup bindings in withDefaults() arguments', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const defaultLabel = 'fallback';
            const props = withDefaults(defineProps<{
                label?: string;
            }>(), {
                label: defaultLabel,
            });
            const count = props.label.length;

            swDefinePublic({
                count,
            });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'base-props-local-default.vue')).toThrow(
            'withDefaults() arguments are hoisted outside the Shopware setup callback and must not reference local setup bindings.',
        );
    });

    it('rejects destructured withDefaults() with the generic props-object message', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const { initialCount } = withDefaults(defineProps<{
                initialCount?: number;
            }>(), {
                initialCount: 3,
            });
            const count = initialCount;

            swDefinePublic({
                count,
            });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'base-destructured-props-with-defaults.vue')).toThrow(
            'Destructuring the props object is not supported in Shopware setup blocks.',
        );
    });

    it('keeps defineProps() wrapped in a TypeScript as expression', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const props = defineProps<{ initialCount?: number }>() as { initialCount?: number };
            const count = props.initialCount ?? 0;

            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'sw-my-component.vue').code;

        expect(result).toContain(
            'const __swSetupAuthor_props = defineProps<{ initialCount?: number }>() as { initialCount?: number };',
        );
        expect(result).toContain('props: __swSetupAuthor_props,');
        expect(result.match(/defineProps/g)).toHaveLength(1);
    });

    it('keeps withDefaults() wrapped in a TypeScript satisfies expression', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const props = withDefaults(defineProps<{
                initialCount?: number;
            }>(), {
                initialCount: 3,
            }) satisfies { initialCount: number };
            const count = props.initialCount;

            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-props-satisfies.vue').code;

        expect(result).toContain(`const __swSetupAuthor_props = withDefaults(defineProps<{
    initialCount?: number;
}>(), {
    initialCount: 3,
}) satisfies { initialCount: number };`);
        expect(result.match(/defineProps/g)).toHaveLength(1);
        expect(result.match(/withDefaults/g)).toHaveLength(1);
    });

    it('ignores nested props macros like Vue compiler-sfc does', () => {
        const source = stripIndent`
            <script setup lang="ts">
            function readProps() {
                return defineProps<{ initialCount?: number }>();
            }

            function readPropsWithDefaults() {
                return withDefaults(defineProps<{ label?: string }>(), {
                    label: 'fallback',
                });
            }

            const count = 1;

            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-nested-props-macros.vue').code;

        // The nested macro calls are function-locals, so they stay untouched and are never treated as
        // the props declaration macro.
        expect(result).toContain('return defineProps<{ initialCount?: number }>();');
        expect(result).toContain(`return withDefaults(defineProps<{ label?: string }>(), {
        label: 'fallback',
    });`);
        expect(result).not.toContain('props: __swSetupAuthor_props');
    });

    it('rejects a setup binding that shares a declared prop name (object form)', () => {
        const source = stripIndent`
            <script setup>
            const props = defineProps({ title: String });
            const title = props.title;
            swDefinePublic({ title });
            </script>
        `;

        // Native Vue allows this, but the extendable setup runtime strips declared prop keys from
        // returned state, so the binding would be deleted and the template read undefined.
        expect(() => transformShopwareSetupSfc(source, 'base-prop-name-collision.vue')).toThrow(
            'Setup binding "title" has the same name as a declared prop.',
        );
    });

    it('rejects a setup binding that shares a declared prop name (type-literal form)', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const props = defineProps<{ title?: string }>();
            const title = 'local';
            swDefinePublic({ title });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'base-prop-name-collision-type.vue')).toThrow(
            'Setup binding "title" has the same name as a declared prop.',
        );
    });

    it('rejects a props type that references a runtime binding kept inside the setup body', () => {
        const source = stripIndent`
            <script setup lang="ts">
            enum Kind { A, B }
            const props = defineProps<{ kind: Kind }>();
            const kindLabel = props.kind;
            swDefinePublic({ kindLabel });
            </script>
        `;

        // `enum Kind` is a runtime binding; Vue hoists defineProps<{ kind: Kind }>() to the component
        // options, where that name is unreachable.
        expect(() => transformShopwareSetupSfc(source, 'base-props-type-enum.vue')).toThrow(
            'defineProps() arguments are hoisted outside the Shopware setup callback and must not reference local setup bindings.',
        );
    });
});
