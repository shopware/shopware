/**
 * @sw-package framework
 */

import { stripIndent, transformOrFail } from './helpers';
import { expectOriginalLine, expectOriginalPosition } from './sourcemap-helpers';

describe('build/vue-setup-transform sourcemap original positions', () => {
    it('maps unchanged template expressions to their original template locations', () => {
        expect.hasAssertions();

        const source = stripIndent`
            <template>
                <sw-block name="sw_example_card">
                    <p>{{ headline }}</p>
                </sw-block>
            </template>
            <script setup>
            const headline = 'Hello';

            swDefinePublic({
                headline,
            });
            </script>
        `;

        const result = transformOrFail(source, 'template-expression.vue');

        expectOriginalPosition(result, source, '{{ headline }}', '{{ headline }}');
    });

    it('maps base body code to its original script setup location', () => {
        expect.hasAssertions();

        const source = stripIndent`
            <template>
                <sw-block name="sw_example_card">
                    <p>{{ headline }}</p>
                </sw-block>
            </template>
            <script setup>
            import { computed } from 'vue';

            const headline = computed(() => 'Hello');

            swDefinePublic({
                headline,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-body.vue');

        // The body stays in place (the binding is renamed, but the initializer text is unchanged), so it
        // maps straight back to its original script-setup location.
        expectOriginalPosition(result, source, "computed(() => 'Hello')", "computed(() => 'Hello')");
    });

    it('maps copied override callback body code to its original script setup location', () => {
        expect.hasAssertions();

        const source = stripIndent`
            <template>
                <sw-block extends="sw_example_card">
                    <p>{{ headline }}</p>
                </sw-block>
            </template>
            <script setup>
            import { computed } from 'vue';

            const previousState = useSwPreviousState();
            const headline = computed(() => previousState.headline.value);

            swDefineOverride({
                headline,
            });
            </script>
        `;

        const result = transformOrFail(source, 'override-callback.override.vue');

        expectOriginalPosition(
            result,
            source,
            'const headline = computed(() => previousState.headline.value);',
            'const headline = computed(() => previousState.headline.value);',
        );
    });

    it('maps preserved imports to their original script setup location', () => {
        expect.hasAssertions();

        const source = stripIndent`
            <script setup>
            import { computed } from 'vue';

            const headline = computed(() => 'Hello');

            swDefinePublic({
                headline,
            });
            </script>
        `;

        const result = transformOrFail(source, 'imports.vue');

        expectOriginalLine(result, source, "import { computed } from 'vue';", "import { computed } from 'vue';");
    });

    it('keeps exact positions for copied source when the SFC uses CRLF line endings', () => {
        expect.hasAssertions();

        const source = [
            '<template>',
            '    <sw-block name="sw_example_card">',
            '        <p>{{ headline }}</p>',
            '    </sw-block>',
            '</template>',
            '<script setup>',
            "const headline = 'Hello';",
            '',
            'swDefinePublic({',
            '    headline,',
            '});',
            '</script>',
        ].join('\r\n');

        const result = transformOrFail(source, 'crlf.vue');

        expectOriginalPosition(result, source, '{{ headline }}', '{{ headline }}');
        // The binding is renamed, but the literal is unchanged and maps straight back.
        expectOriginalPosition(result, source, "'Hello';", "'Hello';");
    });

    it('maps teleported base macros to their original script setup location', () => {
        expect.hasAssertions();

        const source = stripIndent`
            <script setup lang="ts">
            defineOptions({
                inheritAttrs: false,
            });

            const props = withDefaults(defineProps<{
                label?: string;
            }>(), {
                label: 'Label',
            });
            const emit = defineEmits<{
                save: [value: string];
            }>();
            const slots = defineSlots<{
                default(props: { label: string }): unknown;
            }>();

            const headline = props.label;

            swDefinePublic({
                headline,
            });
            </script>
        `;

        const result = transformOrFail(source, 'teleported-macros.vue');

        expectOriginalLine(result, source, 'defineOptions({', 'defineOptions({');
        expectOriginalLine(result, source, 'withDefaults(defineProps<{', 'withDefaults(defineProps<{');
        expectOriginalLine(result, source, 'defineEmits<{', 'defineEmits<{');
        expectOriginalLine(result, source, 'defineSlots<{', 'defineSlots<{');
    });

    it('keeps original template mappings around injected data scope attributes', () => {
        expect.hasAssertions();

        const source = stripIndent`
            <template>
                <sw-block name="sw_example_card">
                    <p>{{ headline }}</p>
                </sw-block>
            </template>
            <script setup>
            const headline = 'Hello';

            swDefinePublic({
                headline,
            });
            </script>
        `;

        const result = transformOrFail(source, 'template-data-scope.vue');

        expect(result.code).toContain('<sw-block :data="$dataScope" name="sw_example_card">');
        expectOriginalLine(result, source, 'name="sw_example_card"', 'name="sw_example_card"');
        expectOriginalLine(result, source, '{{ headline }}', '{{ headline }}');
    });

    it('keeps original template mappings around generated override default slot scopes', () => {
        expect.hasAssertions();

        const source = stripIndent`
            <template>
                <sw-block extends="sw_example_card">
                    <p>{{ headline }}</p>
                    <small>{{ info }}</small>
                </sw-block>
            </template>
            <script setup>
            const headline = 'Hello';
            const info = 'Local';

            swDefineOverride({
                headline,
            });
            </script>
        `;

        const result = transformOrFail(source, 'template-slot-merge.override.vue');

        // The transform generates the #default slot scope (forwarding headline + the private info).
        expect(result.code).toContain('__swOverride');
        expectOriginalLine(result, source, 'extends="sw_example_card"', 'extends="sw_example_card"');
        expectOriginalLine(result, source, '{{ headline }}', '{{ headline }}');
        expectOriginalLine(result, source, '{{ info }}', '{{ info }}');
    });

    it('keeps mappings stable when macros, template edits, and script lowering happen together', () => {
        expect.hasAssertions();

        const source = stripIndent`
            <template>
                <sw-block name="sw_example_card">
                    <button @click="emitSave">{{ headline }}</button>
                </sw-block>
            </template>
            <script setup lang="ts">
            import { computed } from 'vue';

            const props = withDefaults(defineProps<{
                label?: string;
            }>(), {
                label: 'Fallback',
            });
            const emit = defineEmits<{
                save: [value: string];
            }>();
            const headline = computed(() => props.label);

            function emitSave(): void {
                emit('save', headline.value);
            }

            swDefinePublic({
                headline,
                emitSave,
            });
            </script>
        `;

        const result = transformOrFail(source, 'combined-edits.vue');

        expectOriginalLine(result, source, "import { computed } from 'vue';", "import { computed } from 'vue';");
        expectOriginalLine(result, source, 'withDefaults(defineProps<{', 'withDefaults(defineProps<{');
        expectOriginalLine(result, source, 'defineEmits<{', 'defineEmits<{');
        // headline and props are both renamed on this line; the computed initializer text is unchanged.
        expectOriginalLine(result, source, 'computed(() =>', 'computed(() =>');
        // emitSave is renamed to its author alias; the signature tail is unchanged.
        expectOriginalLine(result, source, '(): void {', '(): void {');
        expectOriginalLine(result, source, '@click="emitSave"', '@click="emitSave"');
        expectOriginalLine(result, source, '{{ headline }}', '{{ headline }}');
    });
});
