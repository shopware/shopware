/**
 * @sw-package framework
 */

import shopwareSetupVueTransformer from '../../test/transformer/shopwareSetupVueTransformer';
import { stripIndent } from './index.spec/helpers';

describe('test/transformer/shopwareSetupVueTransformer integration', () => {
    it('applies the Shopware setup transform before delegating Vue files to vue-jest', () => {
        const source = stripIndent`
            <template>
                <button type="button" @click="emit('save', count)">
                    {{ label }}: {{ count }}
                </button>
            </template>

            <script setup lang="ts">
            import { ref } from 'vue';

            const props = withDefaults(defineProps<{
                label?: string,
            }>(), {
                label: 'Counter',
            });
            const emit = defineEmits<{
                save: [value: number],
            }>();
            const count = ref(1);

            swDefinePublic({
                count,
            });
            </script>
        `;

        const transformed = shopwareSetupVueTransformer.process(
            source,
            '/administration/src/sw-jest-transform-fixture.vue',
            { config: {} },
            { instrument: false },
        ) as string | { code: string };
        const code = typeof transformed === 'string' ? transformed : transformed.code;

        expect(code).toContain('Shopware.Component.attachOverrides(');
        expect(code).toContain("'sw-jest-transform-fixture'");
        expect(code).toContain('props: {');
        expect(code).toContain('emits: ["save"]');
        expect(code).toContain('exports.default');
        expect(code).not.toContain('swDefinePublic');
    });
});
