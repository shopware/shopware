/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const tsParser = require('@typescript-eslint/parser');
const vueParser = require('vue-eslint-parser');
/**
 * Rule under test, typed so fixture shape errors are easier to spot.
 *
 * @type {import('eslint').Rule.RuleModule}
 */
const rule = require('./valid-shopware-setup');

/**
 * Shared tester configured for Vue SFC script parsing.
 *
 * @type {import('eslint').RuleTester}
 */
const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
        parser: vueParser,
        parserOptions: {
            parser: {
                ts: tsParser,
                tsx: tsParser,
            },
        },
    },
});

ruleTester.run('valid-shopware-setup', rule, {
    valid: [
        {
            filename: 'base.vue',
            code: `<script setup>
const count = 1;
swDefinePublic({ count });
</script>`,
        },
        {
            filename: 'base-props.vue',
            code: `<script setup lang="ts">
const props = defineProps<{ initialCount?: number }>();
const count = props.initialCount ?? 0;
swDefinePublic({ count });
</script>`,
        },
        {
            filename: 'base-bare-props.vue',
            code: `<script setup>
defineProps();
const count = 1;
swDefinePublic({ count });
</script>`,
        },
        {
            filename: 'base-props-with-defaults.vue',
            code: `<script setup lang="ts">
const props = withDefaults(defineProps<{ initialCount?: number }>(), {
    initialCount: 1,
});
const count = props.initialCount;
swDefinePublic({ count });
</script>`,
        },
        {
            filename: 'base-props-with-imported-defaults.vue',
            code: `<script setup lang="ts">
import { defaultCount } from './defaults';

const props = withDefaults(defineProps<{ initialCount?: number }>(), {
    initialCount: defaultCount,
});
const count = props.initialCount;
swDefinePublic({ count });
</script>`,
        },
        {
            filename: 'base-emits.vue',
            code: `<script setup lang="ts">
const emit = defineEmits<{ save: [id: string] }>();
const count = 1;
swDefinePublic({ count });
</script>`,
        },
        {
            filename: 'base-expose.vue',
            code: `<script setup>
function focus() {}
defineExpose({ focus });
const count = 1;
swDefinePublic({ count });
</script>`,
        },
        {
            filename: 'base-slots.vue',
            code: `<script setup lang="ts">
const slots = defineSlots<{ default(props: { count: number }): unknown }>();
const count = slots.default ? 1 : 0;
swDefinePublic({ count });
</script>`,
        },
        {
            filename: 'base-options.vue',
            code: `<script setup>
defineOptions({ inheritAttrs: false });
const count = 1;
swDefinePublic({ count });
</script>`,
        },
        {
            filename: 'base-options-with-imported-value.vue',
            code: `<script setup>
import { inheritAttrs } from './options';

defineOptions({ inheritAttrs });
const count = 1;
swDefinePublic({ count });
</script>`,
        },
        {
            filename: 'sw-my-component.override.vue',
            code: `<script setup lang="ts">
const previousState = useSwPreviousState();
const doubled: number = previousState.count.value * 2;
swDefineOverride({ doubled });
</script>`,
        },

        // Constraints that belong to Vue, not to this rule. The transform passes these through so Vue's
        // own compiler reports them, which means the rule must not flag them.
        //
        // A destructured props macro is left in place: `defineProps()` gets Vue 3.5's
        // reactive-props-destructure rewrite, and `withDefaults()` gets Vue's own "reactive destructure
        // disabled" advice.
        {
            filename: 'base-destructured-props.vue',
            code: `<script setup lang="ts">
const { initialCount = 0 } = defineProps<{ initialCount?: number }>();
const count = initialCount;
swDefinePublic({ count });
</script>`,
        },
        {
            filename: 'base-destructured-props-with-defaults.vue',
            code: `<script setup lang="ts">
const { initialCount = 0 } = withDefaults(defineProps<{ initialCount?: number }>(), {
    initialCount: 3,
});
const count = initialCount;
swDefinePublic({ count });
</script>`,
        },
        // Macro arguments reading a local: Vue lifts macro arguments into the options object and hoists a
        // statically analysable local up there with them, rejecting only what it cannot hoist. Both
        // outcomes are Vue's to report - the transform does not inspect these arguments at all.
        {
            filename: 'base-with-defaults-local-binding.vue',
            code: `<script setup lang="ts">
const defaultCount = 1;
const props = withDefaults(defineProps<{ initialCount?: number }>(), {
    initialCount: defaultCount,
});
const count = props.initialCount;
swDefinePublic({ count });
</script>`,
        },
        {
            filename: 'base-define-props-local-binding.vue',
            code: `<script setup lang="ts">
const defaultCount = 1;
const props = defineProps({
    initialCount: {
        default: defaultCount,
    },
});
const count = props.initialCount;
swDefinePublic({ count });
</script>`,
        },
        {
            filename: 'base-define-emits-local-binding.vue',
            code: `<script setup lang="ts">
const events = ['save'];
const emit = defineEmits(events);
const count = 1;
swDefinePublic({ count });
</script>`,
        },
        {
            filename: 'base-define-options-local-binding.vue',
            code: `<script setup>
const inheritAttrs = false;
defineOptions({
    inheritAttrs,
});
const count = 1;
swDefinePublic({ count });
</script>`,
        },
    ],

    invalid: [
        {
            filename: 'override-props.override.vue',
            code: `<script setup>
const props = defineProps();
swDefineOverride({});
</script>`,
            errors: [
                {
                    message: 'defineProps() is only supported in base Shopware setup blocks.',
                },
            ],
        },
        {
            filename: 'override-props-with-defaults.override.vue',
            code: `<script setup lang="ts">
const props = withDefaults(defineProps<{ label?: string }>(), {
    label: 'fallback',
});
swDefineOverride({});
</script>`,
            errors: [
                {
                    message: 'withDefaults() is only supported in base Shopware setup blocks.',
                },
            ],
        },
        {
            filename: 'override-emits.override.vue',
            code: `<script setup>
const emit = defineEmits(['save']);
swDefineOverride({});
</script>`,
            errors: [
                {
                    message: 'defineEmits() is only supported in base Shopware setup blocks.',
                },
            ],
        },
        {
            filename: 'override-expose.override.vue',
            code: `<script setup>
defineExpose({});
swDefineOverride({});
</script>`,
            errors: [
                {
                    message: 'defineExpose() is only supported in base Shopware setup blocks.',
                },
            ],
        },
        {
            filename: 'override-slots.override.vue',
            code: `<script setup>
const slots = defineSlots();
swDefineOverride({});
</script>`,
            errors: [
                {
                    message: 'defineSlots() is only supported in base Shopware setup blocks.',
                },
            ],
        },
        {
            filename: 'override-options.override.vue',
            code: `<script setup>
defineOptions({ inheritAttrs: false });
swDefineOverride({});
</script>`,
            errors: [
                {
                    message: 'defineOptions() is only supported in base Shopware setup blocks.',
                },
            ],
        },
        {
            filename: 'dynamic-key.vue',
            // Also pins the loc math: a full-range error underlines the whole offending property.
            code: `<script setup>
const count = 1;
swDefinePublic({ [dynamicKey]: count });
</script>`,
            errors: [
                {
                    message:
                        'swDefinePublic() only supports shorthand bindings such as { a, b }. Renaming and string or computed keys (for example { a: b } or { \'a\': b }) are not supported.',
                    line: 3,
                    column: 18,
                    endLine: 3,
                    endColumn: 37,
                },
            ],
        },
        {
            filename: 'override-public.override.vue',
            code: `<script setup>
const count = 1;
swDefinePublic({ count });
swDefineOverride({ count });
</script>`,
            errors: [
                {
                    message:
                        'swDefinePublic() is a Shopware setup compile-time macro for base components. '
                        + 'It declares which setup bindings are public and may be replaced by overrides. '
                        + 'Override components must use swDefineOverride() to declare replacement bindings instead.',
                },
            ],
        },
        {
            filename: 'base-override.vue',
            code: `<script setup>
const count = 1;
swDefineOverride({ count });
</script>`,
            errors: [
                {
                    message:
                        'swDefineOverride() is a Shopware setup compile-time macro for override components. '
                        + 'It declares which base component bindings this override replaces. '
                        + 'Base components must use swDefinePublic() to expose overrideable setup bindings instead.',
                },
            ],
        },
        {
            filename: 'reserved-override-private-variable.override.vue',
            code: `<script setup>
const __swOverride = {};
swDefineOverride({});
</script>`,
            errors: [
                {
                    message: '"__swOverride" is reserved for Shopware override-private state and must not be declared or imported.',
                },
            ],
        },
        {
            filename: 'reserved-override-private-import.vue',
            code: `<script setup>
import { __swOverride } from './state';

const count = 1;
swDefinePublic({ count });
</script>`,
            errors: [
                {
                    message: '"__swOverride" is reserved for Shopware override-private state and must not be declared or imported.',
                },
            ],
        },
    ],
});
