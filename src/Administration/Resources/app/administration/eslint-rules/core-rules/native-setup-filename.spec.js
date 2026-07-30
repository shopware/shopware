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
const rule = require('./native-setup-filename');

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

const setupBlock = `<script setup>
const count = 1;
swDefinePublic({ count });
</script>`;

ruleTester.run('native-setup-filename', rule, {
    valid: [
        {
            filename: 'sw-product-list.vue',
            code: setupBlock,
        },
        {
            filename: 'swag-my-widget.override.vue',
            code: `<script setup>
swDefineOverride({});
</script>`,
        },
        {
            // The name comes from the directory for an index file, so `index` itself is never the name.
            filename: '/plugin/src/sw-thing/index.vue',
            code: setupBlock,
        },
        {
            // Single segment is left to vue/multi-word-component-names, which reports it with a message
            // about the Vue convention rather than about the filename.
            filename: 'dashboard.vue',
            code: setupBlock,
        },
        {
            // Only SFCs derive a component name from their filename.
            filename: 'Some_Helper.ts',
            code: 'export const value = 1;',
        },
    ],
    invalid: [
        {
            filename: 'sw_thing.vue',
            code: setupBlock,
            errors: [
                {
                    messageId: 'invalidName',
                    data: { componentName: 'sw_thing' },
                },
            ],
        },
        {
            filename: 'SwThing.vue',
            code: setupBlock,
            errors: [
                {
                    messageId: 'invalidName',
                    data: { componentName: 'SwThing' },
                },
            ],
        },
        {
            filename: 'sw thing.override.vue',
            code: `<script setup>
swDefineOverride({});
</script>`,
            errors: [
                {
                    messageId: 'invalidName',
                    data: { componentName: 'sw thing' },
                },
            ],
        },
        {
            // The directory name is what gets checked for an index file.
            filename: '/plugin/src/Bad_Dir/index.vue',
            code: setupBlock,
            errors: [
                {
                    messageId: 'invalidName',
                    data: { componentName: 'Bad_Dir' },
                },
            ],
        },
    ],
});
