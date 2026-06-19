/**
 * @sw-package framework
 */

import { stripIndent, transformOrFail } from './helpers';
import {
    expectGeneratedTokenUnmapped,
    expectUnmapped,
} from './sourcemap-helpers';

describe('build/vue-setup-transform sourcemap generated code', () => {
    it('does not map generated setup input replacements to user-authored macro calls', () => {
        expect.hasAssertions();

        const source = stripIndent`
            <script setup lang="ts" sw-component="sw-my-component">
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

        const result = transformOrFail(source, 'setup-input-replacements.vue');

        expectGeneratedTokenUnmapped(result, '(__shopwareProps)');
        expectGeneratedTokenUnmapped(result, '(__shopwareContext.emit)');
        expectGeneratedTokenUnmapped(result, '(__shopwareContext.slots)');
    });

    it('does not map injected data scope attributes to user-authored template source', () => {
        expect.hasAssertions();

        const source = stripIndent`
            <template>
                <sw-block name="sw_example_card">
                    <p>{{ headline }}</p>
                </sw-block>
            </template>
            <script setup sw-component="sw-my-component">
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

    it('does not map merged override default slot aliases to user-authored template source', () => {
        expect.hasAssertions();

        const source = stripIndent`
            <template>
                <sw-block extends="sw_example_card" #default="{ headline }">
                    <p>{{ headline }}</p>
                    <small>{{ info }}</small>
                </sw-block>
            </template>
            <script setup sw-override="sw-my-component">
            const headline = 'Hello';
            const info = 'Local';

            swDefineOverride({
                headline,
            });
            </script>
        `;

        const result = transformOrFail(source, 'template-slot-merge.vue');

        expect(result.code).toContain('__swOverride');
        expectGeneratedTokenUnmapped(result, '__swOverride');
    });

    it('does not map generated no-template override fallback render functions', () => {
        expect.hasAssertions();

        const source = `<script setup sw-override="sw-my-component">
const headline = 'Hello';

swDefineOverride({
    headline,
});
</script>`;

        const result = transformOrFail(source, 'no-template-override.vue');

        expectUnmapped(result, 'return () => null;');
    });

    it('does not map generated runtime bridge code to user-authored source', () => {
        expect.hasAssertions();

        const source = `<script setup sw-override="sw-my-component">
const headline = 'Hello';

swDefineOverride({
    headline,
});
</script>`;

        const result = transformOrFail(source, 'generated-bridge.vue');

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
            <script setup lang="ts" sw-component="sw-my-component">
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
        expectGeneratedTokenUnmapped(result, '(__shopwareProps)');
        expectGeneratedTokenUnmapped(result, '(__shopwareContext.emit)');
    });
});
