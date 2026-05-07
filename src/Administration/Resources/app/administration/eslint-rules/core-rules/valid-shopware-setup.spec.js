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
            filename: 'override.vue',
            code: `<script setup lang="ts" sw-override="sw-my-component">
const previousState = useSwPreviousState();
const doubled: number = previousState.count.value * 2;
</script>`,
        },
    ],

    invalid: [
        {
            filename: 'macro.vue',
            code: `<script setup sw-component="sw-my-component">
const props = defineProps();
</script>`,
            errors: [
                {
                    message: 'Vue macro defineProps() is not supported inside Shopware setup blocks.',
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
</script>`,
            errors: [
                {
                    message: 'swDefinePublic() is only valid in base Shopware setup blocks.',
                },
            ],
        },
    ],
});
