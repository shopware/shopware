/**
 * @sw-package framework
 */

import {
    expectVueCompilerScriptToCompile,
    stripIndent,
    transformOrFail,
    transformShopwareSetupSfc,
} from './helpers';

describe('build/vue-setup-transform base props macros', () => {
    it('keeps base defineProps() outside the extendable setup callback and passes props into the bridge', () => {
        const source = stripIndent`
            <template><div>{{ count }}</div></template>
            <script setup lang="ts" sw-component="sw-my-component">
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

        const result = transformOrFail(source, 'base-props.vue').code;

        expect(result).toContain(`const props = defineProps<{
    initialCount?: number;
}>();`);
        expect(result).toContain('Shopware.Component.createExtendableSetup(');
        expect(result).toContain("name: 'sw-my-component'");
        expect(result).toContain('props: props,');
        expect(result).toContain('const props = (__shopwareProps);');
        expect(result).toContain('const count = ref(props.initialCount ?? 0);');
        expect(result).not.toContain('const useSwProps =');
        expect(result.indexOf('const props = defineProps')).toBeLessThan(
            result.indexOf('Shopware.Component.createExtendableSetup('),
        );
    });

    it('keeps local prop type declarations available for hoisted defineProps()', () => {
        const source = stripIndent`
            <script setup lang="ts" sw-component="sw-my-component">
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

        expect(result.indexOf('interface Props')).toBeLessThan(result.indexOf('const props = defineProps<Props>()'));
        expect(result.indexOf('interface Props')).toBeLessThan(
            result.indexOf('Shopware.Component.createExtendableSetup('),
        );
        expectVueCompilerScriptToCompile(result, 'base-props-local-type.vue');
    });

    it('replaces defineProps() destructuring inside the extendable setup callback', () => {
        const source = stripIndent`
            <script setup sw-component="sw-my-component">
            const { initialCount = 0 } = defineProps();
            const count = initialCount;
            
            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-destructured-props.vue').code;

        expect(result).toContain('const props = defineProps();');
        expect(result).toContain('const { initialCount = 0 } = (__shopwareProps);');
        expect(result).toContain('private: {\n                initialCount,\n            }');
    });

    it('returns destructured defineProps() bindings for template access', () => {
        const source = stripIndent`
            <template><p>{{ initialCount }}</p></template>
            <script setup sw-component="sw-my-component">
            const { initialCount = 0 } = defineProps();
            const count = 1;
            
            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-destructured-props-template.vue').code;

        expect(result).toContain('const { initialCount = 0 } = (__shopwareProps);');
        expect(result).toContain('private: {\n                initialCount,\n            }');
    });

    it('keeps bare defineProps() outside the callback when the generated props binding name is taken', () => {
        const source = stripIndent`
            <script setup sw-component="sw-my-component">
            defineProps();
            
            function props() {
                return 'local binding';
            }
            
            const count = props().length;
            
            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-bare-props-collision.vue').code;

        expect(result).toContain('const props2 = defineProps();');
        expect(result).toContain('Shopware.Component.createExtendableSetup(');
        expect(result).toContain("name: 'sw-my-component'");
        expect(result).toContain('props: props2,');
        expect(result).toContain('(__shopwareProps);');
        expect(result).toContain("return 'local binding';");
        expect(result).toContain('private: {\n                props,\n            }');
    });

    it('keeps base withDefaults(defineProps()) outside the extendable setup callback', () => {
        const source = stripIndent`
            <script setup lang="ts" sw-component="sw-my-component">
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

        const result = transformOrFail(source, 'base-props-with-defaults.vue').code;

        expect(result).toContain(`const props = withDefaults(defineProps<{
    initialCount?: number;
    labels?: string[];
}>(), {
    initialCount: 3,
    labels: () => ['main'],
});`);
        expect(result).toContain('Shopware.Component.createExtendableSetup(');
        expect(result).toContain("name: 'sw-my-component'");
        expect(result).toContain('props: props,');
        expect(result).toContain('const props = (__shopwareProps);');
        expect(result).toContain('const count = props.initialCount;');
        expect(result.match(/defineProps/g)).toHaveLength(1);
        expect(result.match(/withDefaults/g)).toHaveLength(1);
    });

    it('keeps static local defaults available for hoisted withDefaults()', () => {
        const source = stripIndent`
            <script setup lang="ts" sw-component="sw-my-component">
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

        const result = transformOrFail(source, 'base-props-local-default.vue').code;

        expect(result.indexOf("const defaultLabel = 'fallback';")).toBeLessThan(
            result.indexOf('const props = withDefaults(defineProps<{'),
        );
        expect(result).toContain('private: {\n                defaultLabel,\n            }');
        expectVueCompilerScriptToCompile(result, 'base-props-local-default.vue');
    });

    it('replaces withDefaults() destructuring inside the extendable setup callback', () => {
        const source = stripIndent`
            <script setup lang="ts" sw-component="sw-my-component">
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

        const result = transformOrFail(source, 'base-destructured-props-with-defaults.vue').code;

        expect(result).toContain(`const props = withDefaults(defineProps<{
    initialCount?: number;
}>(), {
    initialCount: 3,
});`);
        expect(result).toContain('const { initialCount } = (__shopwareProps);');
        expect(result).toContain('private: {\n                initialCount,\n            }');
        expect(result.match(/defineProps/g)).toHaveLength(1);
        expect(result.match(/withDefaults/g)).toHaveLength(1);
    });

    it('returns destructured withDefaults() bindings for template access', () => {
        const source = stripIndent`
            <template><p>{{ initialCount }}</p></template>
            <script setup lang="ts" sw-component="sw-my-component">
            const { initialCount } = withDefaults(defineProps<{
                initialCount?: number;
            }>(), {
                initialCount: 3,
            });
            const count = 1;
            
            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-destructured-with-defaults-template.vue').code;

        expect(result).toContain('const { initialCount } = (__shopwareProps);');
        expect(result).toContain('private: {\n                initialCount,\n            }');
    });

    it('supports defineProps() wrapped in a TypeScript as expression', () => {
        const source = stripIndent`
            <script setup lang="ts" sw-component="sw-my-component">
            const props = defineProps<{ initialCount?: number }>() as { initialCount?: number };
            const count = props.initialCount ?? 0;
            
            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-props-as.vue').code;

        expect(result).toContain('const props = defineProps<{ initialCount?: number }>();');
        expect(result).toContain('Shopware.Component.createExtendableSetup(');
        expect(result).toContain("name: 'sw-my-component'");
        expect(result).toContain('props: props,');
        expect(result).toContain(
            'const props = (__shopwareProps) as { initialCount?: number };',
        );
        expect(result.match(/defineProps/g)).toHaveLength(1);
    });

    it('supports withDefaults() wrapped in a TypeScript satisfies expression', () => {
        const source = stripIndent`
            <script setup lang="ts" sw-component="sw-my-component">
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

        expect(result).toContain(`const props = withDefaults(defineProps<{
    initialCount?: number;
}>(), {
    initialCount: 3,
});`);
        expect(result).toContain(
            'const props = (__shopwareProps) satisfies { initialCount: number };',
        );
        expect(result.match(/defineProps/g)).toHaveLength(1);
        expect(result.match(/withDefaults/g)).toHaveLength(1);
    });

    it('rejects multiple base props macro declarations', () => {
        const source = stripIndent`
            <script setup lang="ts" sw-component="sw-my-component">
            const props = defineProps<{ count?: number }>();
            const propsWithDefaults = withDefaults(defineProps<{ label?: string }>(), {
                label: 'fallback',
            });
            const count = props.count ?? propsWithDefaults.label.length;
            
            swDefinePublic({
                count,
            });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'base-duplicate-props-macros.vue')).toThrow(
            'Only one props declaration macro is allowed in a base Shopware setup block.',
        );
    });

    it('ignores nested props macros like Vue compiler-sfc does', () => {
        const source = stripIndent`
            <script setup lang="ts" sw-component="sw-my-component">
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

        expect(result).toContain(`function readProps() {
            return defineProps<{ initialCount?: number }>();
        }`);
        expect(result).toContain(`function readPropsWithDefaults() {
            return withDefaults(defineProps<{ label?: string }>(), {
                label: 'fallback',
            });
        }`);
        expect(result).not.toContain('const props = defineProps');
        expect(result).not.toContain('const props = withDefaults');
        expect(result).not.toContain('(__shopwareProps)');
    });
});
