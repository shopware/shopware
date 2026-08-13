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

import { expectVueCompilerScriptToCompile, stripIndent, transformOrFail } from './helpers';

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

    it('collects destructured defineSlots() bindings (unlike props, which are left for Vue)', () => {
        const source = stripIndent`
            <script setup>
            const { default: defaultSlot } = defineSlots();
            const count = defaultSlot ? 1 : 0;
            swDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'base-slots-destructure.vue').code;

        // defineSlots() is not a props macro, so its destructured bindings are ordinary runtime state:
        // renamed to their author alias and re-exposed from the footer.
        expect(result).toContain('const { default: __swSetupAuthor_defaultSlot } = defineSlots();');
        expect(result).toContain('defaultSlot: __swSetupAuthor_defaultSlot');
    });

    it('leaves a hoistable local in defineOptions() arguments to Vue, which hoists it and compiles', () => {
        const source = stripIndent`
            <script setup>
            const inheritAttrs = false;
            defineOptions({ inheritAttrs });
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        // Same as defineProps: Vue lifts `const inheritAttrs = false` to module scope next to the emitted
        // option, so this compiles. The transform must not pre-empt it.
        const result = transformOrFail(source, 'base-options-local.vue').code;

        expect(result).toContain('defineOptions({ inheritAttrs: __swSetupAuthor_inheritAttrs })');
        expectVueCompilerScriptToCompile(result, 'base-options-local.vue');
    });
});
