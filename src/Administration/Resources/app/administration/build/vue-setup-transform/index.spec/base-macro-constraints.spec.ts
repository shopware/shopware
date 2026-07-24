/**
 * @sw-package framework
 */

/**
 * Base lowering leaves defineEmits/defineSlots/defineOptions/defineExpose in place and lets their
 * bindings flow through the generic rename + footer path (covered by base-transform.spec and
 * rename-pass.spec). The only macro-specific validation left for these is the Vue-invalid argument
 * guard on defineOptions. defineProps keeps its own file — it is the one macro with dedicated handling
 * (props forwarded to the footer, destructuring, prop-name collisions).
 */

import { stripIndent, transformOrFail, transformShopwareSetupSfc } from './helpers';

describe('build/vue-setup-transform base non-props macros', () => {
    it('keeps every non-props macro in place with its binding renamed and re-exposed', () => {
        const source = stripIndent`
            <script setup lang="ts">
            defineOptions({ inheritAttrs: false });
            defineSlots<{ default(): unknown }>();
            const emit = defineEmits<{ save: [] }>();
            const count = 1;
            defineExpose({ count });
            swDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'base-macros.vue').code;

        // Macros are untouched; Vue's own compiler handles them downstream. Only the emit binding is
        // aliased and re-exposed from the footer, and defineExpose keeps referencing the aliased binding.
        expect(result).toContain('defineOptions({ inheritAttrs: false });');
        expect(result).toContain('defineSlots<{ default(): unknown }>();');
        expect(result).toContain('const __swSetupAuthor_emit = defineEmits<{ save: [] }>();');
        expect(result).toContain('defineExpose({ count: __swSetupAuthor_count });');
        expect(result).toContain('emit: __swSetupAuthor_emit');
    });

    it('rejects local setup bindings in defineOptions() arguments', () => {
        const source = stripIndent`
            <script setup>
            const inheritAttrs = false;
            defineOptions({ inheritAttrs });
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        // Vue hoists defineOptions() to the component options object, so referencing a local setup
        // binding in its argument is invalid — we reject it with an actionable message up front.
        expect(() => transformShopwareSetupSfc(source, 'base-options-local.vue')).toThrow(
            'defineOptions() arguments are hoisted outside the Shopware setup callback and must not reference local setup bindings.',
        );
    });
});
