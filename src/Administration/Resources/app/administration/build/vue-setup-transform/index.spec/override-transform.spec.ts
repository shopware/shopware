/**
 * @sw-package framework
 */

/**
 * Covers the end-to-end override lowering: how an override `<script setup>` becomes a module that
 * registers the setup override, the `swDefineOverride` return payload, and which statements are hoisted
 * out of the callback. Template forwarding lives in override-template.spec.ts.
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

        // The one end-to-end assertion for override lowering: the author's <script setup> becomes a plain
        // <script> that registers the callback at module scope, next to the module-scope Symbol()
        // namespace; every override local reaches the block through the generated `#default` scope and
        // the `__swOverride` payload. Whitespace-insensitive, since the transform does not beautify.
        const fileKey =
            /\.override\('sw-example', '([0-9a-f]{8})'/.exec(transformOrFail(source, 'sw-example.override.vue').code)?.[1] ??
            '';
        const expected = stripWhitespace`
            <template>
                <sw-block extends="sw_example_headline" #default="{ __swOverride: { [__swSetupNamespace]: { suffix, previousState } = {} } = {}, headline }">
                    <h1>{{ headline }} - {{ suffix }}</h1>
                </sw-block>
            </template>
            <script lang="ts">
            import { computed } from 'vue';

            const __swSetupNamespace = Symbol('sw-example.override');
            globalThis.Shopware.Component.__setupRuntime.v1.override('sw-example', '${fileKey}', (__swSetupPreviousState, __swSetupProps, __swSetupContext) => {
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
                        previousState,
                    },
                },
            };
            });
            </script>
            <script setup lang="ts">/* exposes the module-scope bindings to the template */</script>
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

        // The registration component is still mounted, and Vue warns about one without a template.
        expect(result).toContain('<template><!-- Shopware override registration component --></template>');
        expect(result).toMatch(
            /globalThis\.Shopware\.Component\.__setupRuntime\.v1\.override\('sw-my-component', '[0-9a-f]{8}', \(__swSetupPreviousState, __swSetupProps, __swSetupContext\) => \{/,
        );
        expect(result).toContain('const useSwPreviousState = () => __swSetupPreviousState;');
        expect(result).toContain('const useSwProps = () => __swSetupProps;');
        expect(result).toContain('const useSwContext = () => __swSetupContext;');
        expect(result).toContain('return {};');
        expectVueCompilerScriptToCompile(result, 'sw-my-component.override.vue');
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
        expect(result.code).toContain(".__setupRuntime.v1.override('component-name', '");
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

    it('hoists only what cannot live in the callback: imports, declare statements and type exports', () => {
        const source = stripIndent`
            <script setup lang="ts">
            type Inner = { a: string };
            const props = useSwProps<Inner>();
            export type Outer = Inner;
            declare const injected: string;
            import { ref } from 'vue';
            interface Local { b: string }
            const label = ref<Local | null>(null);

            swDefineOverride({ label });
            </script>
        `;

        const result = transformOrFail(source, 'typed.override.vue').code;
        const callbackStart = result.indexOf('.override(');

        expect(result.indexOf('export type Outer = Inner;')).toBeLessThan(callbackStart);
        expect(result.indexOf('declare const injected: string;')).toBeLessThan(callbackStart);
        expect(result.indexOf("import { ref } from 'vue';")).toBeLessThan(callbackStart);
        // Types are legal inside a function body and stay where the author wrote them.
        expect(result.indexOf('type Inner = { a: string };')).toBeGreaterThan(callbackStart);
        expect(result.indexOf('interface Local')).toBeGreaterThan(callbackStart);
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
