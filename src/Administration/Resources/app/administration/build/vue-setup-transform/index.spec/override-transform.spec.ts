/**
 * @sw-package framework
 */

/**
 * Covers the end-to-end override lowering with no template involved: how an override `<script setup>`
 * becomes a hidden component that registers the setup override, the `swDefineOverride` return payload,
 * and how imports and type declarations are preserved in the generated component.
 *
 * Template-driven forwarding lives in override-template.spec.ts; the destructuring-pattern edge cases
 * in override-template-patterns.spec.ts.
 */

import { expectVueCompilerScriptToCompile, stripIndent, stripWhitespace, transformOrFail } from './helpers';

describe('build/vue-setup-transform override transforms', () => {
    it('pins the whole generated output for an override with an <sw-block extends> and forwarded locals', () => {
        const source = stripIndent`
            <template>
                <sw-block extends="sw_example_headline">
                    <h1>{{ headline }} - {{ suffix }}</h1>
                </sw-block>
            </template>
            <script setup lang="ts">
            import { computed } from 'vue';

            const previousState = useSwPreviousState();
            const suffix = computed(() => '!');
            const headline = computed(() => previousState.title.value);

            swDefineOverride({
                headline,
            });
            </script>
        `;

        // The leading plain <script> is the extension-targets registry: it runs at module eval, which is
        // what puts the block names in place before the Twig templates are resolved.
        //
        // The one end-to-end assertion for override lowering, covering the three generated constructs that
        // only co-occur on the <sw-block extends> path: the module-root Symbol() namespace, the
        // `__swOverride` payload keyed by it, and the `#default` slot scope that forwards the
        // override-local `suffix` into the block content. Imports are lifted out of the callback; the
        // author body is preserved inside it.
        //
        // Whitespace-insensitive on both sides - the transform does not beautify its output, so its
        // blank-line residue is not behaviour. The Vue round-trip below guards the token sequence.
        const expected = stripWhitespace`
            <script lang="ts">
            Shopware.Component.registerNativeExtensionTargets?.({
                component: 'sw-example',
                blocks: [
                    'sw_example_headline',
                ],
            });
            </script>
            <template>
                <sw-block extends="sw_example_headline" #default="{ __swOverride: { [__swSetupNamespace]: { suffix } }, headline }">
                    <h1>{{ headline }} - {{ suffix }}</h1>
                </sw-block>
            </template>
            <script setup lang="ts">
            import { computed } from 'vue';

            const __swSetupNamespace = Symbol('sw-example.override');

            Shopware.Component.overrideComponentSetup()('sw-example', (__swSetupPreviousState, __swSetupProps, __swSetupContext) => {
            const useSwPreviousState = () => __swSetupPreviousState;
            const useSwProps = () => __swSetupProps;
            const useSwContext = () => __swSetupContext;

            const previousState = useSwPreviousState();
            const suffix = computed(() => '!');
            const headline = computed(() => previousState.title.value);

            return {
                headline,
                __swOverride: {
                    [__swSetupNamespace]: {
                        suffix,
                    },
                },
            };
            });
            </script>
        `;

        const result = transformOrFail(source, 'sw-example.override.vue').code;

        expect(stripWhitespace(result)).toBe(expected);
        expectVueCompilerScriptToCompile(result, 'sw-example.override.vue');
    });

    it('generates a registration template for an override without one', () => {
        const source = stripIndent`
            <script setup>
            const previousState = useSwPreviousState();
            const props = useSwProps();
            const context = useSwContext();

            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'sw-my-component.override.vue').code;

        // A template-less override still has to render: the hidden component only registers its callback
        // once it mounts, and Vue warns about a component with neither template nor render function.
        expect(result).toContain('<template><!-- Shopware override registration component --></template>');
        expect(result).toContain(
            "Shopware.Component.overrideComponentSetup()('sw-my-component', (__swSetupPreviousState, __swSetupProps, __swSetupContext) => {",
        );
        expect(result).toContain('const useSwPreviousState = () => __swSetupPreviousState;');
        expect(result).toContain('const useSwProps = () => __swSetupProps;');
        expect(result).toContain('const useSwContext = () => __swSetupContext;');
        expect(result).toContain('return {};');
        expectVueCompilerScriptToCompile(result, 'sw-my-component.override.vue');
    });

    it('appends the extension-targets registration to a codemod module prelude instead of emitting a second script', () => {
        const source = stripIndent`
            <script data-sfc-migration-module lang="ts">
            export const moduleValue = 1;
            </script>

            <template>
                <sw-block extends="sw_example_headline">
                    <h1>overridden</h1>
                </sw-block>
            </template>

            <script setup lang="ts">
            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'sw-example.override.vue').code;

        // Vue allows exactly one plain <script> beside <script setup>, and the codemod already spends it.
        // A second one would make every migrated override fail to compile, so the registration goes into
        // the prelude that is already there - keeping its own lang attribute rather than a mirrored one.
        expect(result.match(/<script(?![ ]setup)/g)).toHaveLength(1);
        expect(result).toContain('<script data-sfc-migration-module lang="ts">');
        expect(stripWhitespace(result)).toContain(
            stripWhitespace`
                export const moduleValue = 1;
                Shopware.Component.registerNativeExtensionTargets?.({
                    component: 'sw-example',
                    blocks: [
                        'sw_example_headline',
                    ],
                });
                </script>
            `,
        );
        expectVueCompilerScriptToCompile(result, 'sw-example.override.vue');
    });

    it('transforms sw-override blocks in .override.vue files', () => {
        const source = stripIndent`
            <script setup>
            const count = 1;
            swDefineOverride({ count });
            </script>
        `;

        const result = transformOrFail(source, 'component-name.override.vue');

        expect(result.mode).toBe('override');
        expect(result.filename).toBe('component-name.override.vue');
        expect(result.code).toContain("Shopware.Component.overrideComponentSetup()('component-name'");
    });

    it('keeps imports out of returned override state', () => {
        const source = stripIndent`
            <script setup>
            import { computed } from 'vue';

            const doubled = computed(() => 2);

            swDefineOverride({
                doubled,
            });
            </script>
        `;

        const result = transformOrFail(source, 'component.override.vue').code;

        expect(stripWhitespace(result)).toContain(stripWhitespace`
            return {
                doubled,
            };
        `);
        expect(result).not.toContain('computed,');
    });

    it('hoists type declarations as a group so cross-references and type-only exports survive', () => {
        const source = stripIndent`
            <script setup lang="ts">
            type Inner = { a: string };
            export type Outer = Inner;

            const props = useSwProps<Inner>();
            const label = props.a;

            swDefineOverride({ label });
            </script>
        `;

        const result = transformOrFail(source, 'typed.override.vue').code;
        const callbackStart = result.indexOf('Shopware.Component.overrideComponentSetup()');

        // A bare `type`/`interface` would be legal inside the generated callback; `export type` and an
        // ambient `declare` are not. They are hoisted as one group rather than selectively, because a
        // hoisted declaration can reference a preceding one - `export type Outer = Inner` here - and would
        // dangle if that one were left behind in the callback.
        expect(result.indexOf('type Inner = { a: string };')).toBeLessThan(callbackStart);
        expect(result.indexOf('export type Outer = Inner;')).toBeLessThan(callbackStart);
        expect(result).toContain('const props = useSwProps<Inner>();');
        expect(result.match(/type Inner/g)).toHaveLength(1);
        expectVueCompilerScriptToCompile(result, 'typed.override.vue');
    });

    it('uses swDefineOverride() as the explicit override payload and keeps unused local state private', () => {
        const source = stripIndent`
            <script setup>
            import { computed, ref } from 'vue';

            const previousState = useSwPreviousState();
            const body = computed(() => previousState.body.value);
            const localInfo = ref('only for script logic');
            const localHeadline = computed(() => localInfo.value);
            const localFooter = computed(() => localInfo.value);

            swDefineOverride({
                body,
                localHeadline,
                localFooter,
            });
            </script>
        `;

        const result = transformOrFail(source, 'explicit-payload.override.vue').code;

        expect(stripWhitespace(result)).toContain(stripWhitespace`
            return {
                body,
                localHeadline,
                localFooter,
            };
        `);
        expect(result).not.toContain('__swOverride');
        expect(result).not.toContain('localInfo,');
    });
});
