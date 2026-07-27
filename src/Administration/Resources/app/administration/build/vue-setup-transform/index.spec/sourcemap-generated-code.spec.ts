/**
 * @sw-package framework
 */

import { stripIndent, transformOrFail } from './helpers';
import { expectGeneratedTokenUnmapped, expectUnmapped } from './sourcemap-helpers';

describe('build/vue-setup-transform sourcemap generated code', () => {
    it('does not map the generated attachOverrides footer to user source', () => {
        expect.hasAssertions();

        const source = stripIndent`
            <script setup lang="ts">
            const props = defineProps<{
                label: string;
            }>();
            const emit = defineEmits<{
                save: [value: string];
            }>();
            const slots = defineSlots<{
                default(props: { label: string }): unknown;
            }>();

            const headline = props.label;
            emit('save', headline);
            Boolean(slots.default);

            swDefinePublic({
                headline,
            });
            </script>
        `;

        const result = transformOrFail(source, 'generated-footer.vue');

        // The author body stays in place (macros untouched, bindings renamed), so the footer is the only
        // transform-authored code in a base block and the only thing that must stay unmapped. A base
        // block emits no generated headers at all - its body runs as a native <script setup>.
        expectGeneratedTokenUnmapped(result, 'Shopware.Component.attachOverrides({');
    });

    it('does not map the generated override setup-input headers to user source', () => {
        expect.hasAssertions();

        const source = stripIndent`
            <script setup lang="ts">
            const previousState = useSwPreviousState();
            const context = useSwContext();
            const doubled = previousState.count.value * 2;

            swDefineOverride({
                doubled,
            });
            </script>
        `;

        const result = transformOrFail(source, 'generated-headers.override.vue');

        // An override body runs inside a generated callback, so the useSw* helpers are emitted as
        // transform-authored headers above it and must not map back to the author's calls.
        expectGeneratedTokenUnmapped(result, 'const useSwContext = () => __swSetupContext;');
        expectGeneratedTokenUnmapped(result, 'const useSwPreviousState = () => __swSetupPreviousState;');
    });

    it('does not map injected data scope attributes to user-authored template source', () => {
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
        expectGeneratedTokenUnmapped(result, ':data="$dataScope"');
    });

    it('does not map generated override default slot scopes to user-authored template source', () => {
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

        // The whole #default scope is transform-generated, so its tokens must stay unmapped.
        expect(result.code).toContain('__swOverride');
        expectGeneratedTokenUnmapped(result, '__swOverride');
    });

    it('does not map the generated no-template override registration template', () => {
        expect.hasAssertions();

        const source = `<script setup>
const headline = 'Hello';

swDefineOverride({
    headline,
});
</script>`;

        const result = transformOrFail(source, 'no-template-override.override.vue');

        // A template-less override receives a generated comment-only template so the hidden
        // registration component can mount; that injected markup has no author source.
        expectUnmapped(result, '<!-- Shopware override registration component -->');
    });

    it('does not map generated runtime bridge code to user-authored source', () => {
        expect.hasAssertions();

        const source = `<script setup>
const headline = 'Hello';

swDefineOverride({
    headline,
});
</script>`;

        const result = transformOrFail(source, 'generated-bridge.override.vue');

        expectUnmapped(result, 'overrideComponentSetup');
    });

    it('does not map generated code when macros, template edits, and script lowering happen together', () => {
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

        expectGeneratedTokenUnmapped(result, ':data="$dataScope"');
        expectGeneratedTokenUnmapped(result, 'Shopware.Component.attachOverrides({');
    });
});
