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
    ],

    invalid: [
        {
            filename: 'base-destructured-props.vue',
            code: `<script setup lang="ts">
const { initialCount = 0 } = defineProps<{ initialCount?: number }>();
const count = initialCount;
swDefinePublic({ count });
</script>`,
            errors: [
                {
                    message:
                        'Destructuring defineProps() is not supported in Shopware setup blocks: defaults declared through '
                        + 'destructuring (const { count = 1 } = defineProps()) are not applied. Assign the macro to a '
                        + 'variable such as `const props = defineProps(...)` and read `props.<name>`, and use '
                        + '`withDefaults(defineProps(...), { ... })` for defaults.',
                },
            ],
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
            errors: [
                {
                    message:
                        'Destructuring the props object is not supported in Shopware setup blocks. Assign '
                        + '`withDefaults(defineProps(...), { ... })` to a variable such as `const props = ...` and read '
                        + '`props.<name>`.',
                },
            ],
        },
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
            filename: 'base-with-defaults-local-binding.vue',
            code: `<script setup lang="ts">
const defaultCount = 1;
const props = withDefaults(defineProps<{ initialCount?: number }>(), {
    initialCount: defaultCount,
});
const count = props.initialCount;
swDefinePublic({ count });
</script>`,
            errors: [
                {
                    message:
                        'withDefaults() arguments are hoisted outside the Shopware setup callback and must not reference local setup bindings. Use inline literals or imported constants instead.',
                },
            ],
        },
        {
            filename: 'base-with-defaults-local-shorthand.vue',
            code: `<script setup lang="ts">
const initialCount = 1;
const props = withDefaults(defineProps<{ initialCount?: number }>(), {
    initialCount,
});
const count = props.initialCount;
swDefinePublic({ count });
</script>`,
            errors: [
                {
                    message:
                        'withDefaults() arguments are hoisted outside the Shopware setup callback and must not reference local setup bindings. Use inline literals or imported constants instead.',
                },
            ],
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
            errors: [
                {
                    message:
                        'defineProps() arguments are hoisted outside the Shopware setup callback and must not reference local setup bindings. Use inline literals or imported constants instead.',
                },
            ],
        },
        {
            filename: 'base-define-emits-local-binding.vue',
            code: `<script setup lang="ts">
const events = ['save'];
const emit = defineEmits(events);
const count = 1;
swDefinePublic({ count });
</script>`,
            errors: [
                {
                    message:
                        'defineEmits() arguments are hoisted outside the Shopware setup callback and must not reference local setup bindings. Use inline literals or imported constants instead.',
                },
            ],
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
            errors: [
                {
                    message:
                        'defineOptions() arguments are hoisted outside the Shopware setup callback and must not reference local setup bindings. Use inline literals or imported constants instead.',
                },
            ],
        },
        {
            filename: 'dynamic-key.vue',
            code: `<script setup>
const count = 1;
swDefinePublic({ [dynamicKey]: count });
</script>`,
            errors: [
                {
                    message:
                        'swDefinePublic() only supports shorthand bindings such as { a, b }. Renaming and string or computed keys (for example { a: b } or { \'a\': b }) are not supported.',
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
