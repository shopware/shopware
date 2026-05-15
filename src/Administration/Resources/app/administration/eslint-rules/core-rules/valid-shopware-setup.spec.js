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
            filename: 'base-emits.vue',
            code: `<script setup lang="ts" sw-component="sw-my-component">
const emit = defineEmits<{ save: [id: string] }>();
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
            filename: 'dynamic-key.vue',
            code: `<script setup sw-component="sw-my-component">
const count = 1;
swDefinePublic({ [dynamicKey]: count });
</script>`,
            errors: [
                {
                    message:
                        'Computed keys in swDefinePublic() are intentionally unsupported because transform, lint, and type layers need a stable compile-time key.',
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
                    message: 'swDefinePublic() is only valid in base Shopware setup blocks.',
                },
            ],
        },
    ],
});
