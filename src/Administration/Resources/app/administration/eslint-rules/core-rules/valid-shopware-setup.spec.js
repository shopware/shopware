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
            code: `<script setup sw-component="sw-my-component">
const count = 1;
swDefinePublic({ count });
</script>`,
        },
        {
            filename: 'base-props.vue',
            code: `<script setup lang="ts" sw-component="sw-my-component">
const props = defineProps<{ initialCount?: number }>();
const count = props.initialCount ?? 0;
swDefinePublic({ count });
</script>`,
        },
        {
            filename: 'base-bare-props.vue',
            code: `<script setup sw-component="sw-my-component">
defineProps();
const count = 1;
swDefinePublic({ count });
</script>`,
        },
        {
            filename: 'base-destructured-props.vue',
            code: `<script setup sw-component="sw-my-component">
const { initialCount = 0 } = defineProps();
const count = initialCount;
swDefinePublic({ count });
</script>`,
        },
        {
            filename: 'base-props-with-defaults.vue',
            code: `<script setup lang="ts" sw-component="sw-my-component">
const props = withDefaults(defineProps<{ initialCount?: number }>(), {
    initialCount: 1,
});
const count = props.initialCount;
swDefinePublic({ count });
</script>`,
        },
        {
            filename: 'base-props-with-imported-defaults.vue',
            code: `<script setup lang="ts" sw-component="sw-my-component">
import { defaultCount } from './defaults';

const props = withDefaults(defineProps<{ initialCount?: number }>(), {
    initialCount: defaultCount,
});
const count = props.initialCount;
swDefinePublic({ count });
</script>`,
        },
        {
            filename: 'base-destructured-props-with-local-defaults.vue',
            code: `<script setup lang="ts" sw-component="sw-my-component">
const defaultCount = 1;
const { initialCount = defaultCount } = defineProps<{ initialCount?: number }>();
const count = initialCount;
swDefinePublic({ count });
</script>`,
        },
        {
            filename: 'base-emits.vue',
            code: `<script setup lang="ts" sw-component="sw-my-component">
const emit = defineEmits<{ save: [id: string] }>();
const count = 1;
swDefinePublic({ count });
</script>`,
        },
        {
            filename: 'base-expose.vue',
            code: `<script setup sw-component="sw-my-component">
function focus() {}
defineExpose({ focus });
const count = 1;
swDefinePublic({ count });
</script>`,
        },
        {
            filename: 'base-slots.vue',
            code: `<script setup lang="ts" sw-component="sw-my-component">
const slots = defineSlots<{ default(props: { count: number }): unknown }>();
const count = slots.default ? 1 : 0;
swDefinePublic({ count });
</script>`,
        },
        {
            filename: 'base-options.vue',
            code: `<script setup sw-component="sw-my-component">
defineOptions({ inheritAttrs: false });
const count = 1;
swDefinePublic({ count });
</script>`,
        },
        {
            filename: 'base-options-with-imported-value.vue',
            code: `<script setup sw-component="sw-my-component">
import { inheritAttrs } from './options';

defineOptions({ inheritAttrs });
const count = 1;
swDefinePublic({ count });
</script>`,
        },
        {
            filename: 'override.vue',
            code: `<script setup lang="ts" sw-override="sw-my-component">
const previousState = useSwPreviousState();
const doubled: number = previousState.count.value * 2;
swDefineOverride({ doubled });
</script>`,
        },
    ],

    invalid: [
        {
            filename: 'override-props.vue',
            code: `<script setup sw-override="sw-my-component">
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
            filename: 'override-props-with-defaults.vue',
            code: `<script setup lang="ts" sw-override="sw-my-component">
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
            filename: 'override-emits.vue',
            code: `<script setup sw-override="sw-my-component">
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
            filename: 'override-expose.vue',
            code: `<script setup sw-override="sw-my-component">
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
            filename: 'override-slots.vue',
            code: `<script setup sw-override="sw-my-component">
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
            filename: 'override-options.vue',
            code: `<script setup sw-override="sw-my-component">
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
            code: `<script setup lang="ts" sw-component="sw-my-component">
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
            code: `<script setup lang="ts" sw-component="sw-my-component">
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
            code: `<script setup lang="ts" sw-component="sw-my-component">
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
            code: `<script setup lang="ts" sw-component="sw-my-component">
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
            code: `<script setup sw-component="sw-my-component">
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
            code: `<script setup sw-component="sw-my-component">
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
            filename: 'override-public.vue',
            code: `<script setup sw-override="sw-my-component">
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
            code: `<script setup sw-component="sw-my-component">
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
            filename: 'reserved-override-private-variable.vue',
            code: `<script setup sw-override="sw-my-component">
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
            code: `<script setup sw-component="sw-my-component">
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
