const RuleTester = require('eslint').RuleTester
const rule = require('./no-deprecated-component-usage');

const tester = new RuleTester({
    parser: require.resolve('vue-eslint-parser'),
    parserOptions: { ecmaVersion: 2015 }
})

tester.run('no-deprecated-component-usage', rule, {
    valid: [
        {
            name: 'Empty file',
            filename: 'test.html.twig',
            code: ''
        },
        /**
         * mt-button
         */
        {
            name: '"mt-button" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-button>Hello</mt-button>
            </template>`
        },
        {
            name: '"sw-button" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <sw-button>Hello</sw-button>
            </template>`
        },
        {
            name: '"mt-button" new ghost prop usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-button ghost>Hello</mt-button>
            </template>`
        },
        {
            name: 'Ignore wrong "sw-button" usage with old variant prop "ghost"',
            filename: 'test.html.twig',
            code: `
            <template>
                <sw-button variant="ghost">Hello</sw-button>
            </template>`,
        }
    ],
    invalid: [
        {
            name: '"mt-button" wrong ghost prop usage',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-button variant="ghost">Hello</mt-button>
            </template>`,
            output: `
            <template>
                <mt-button ghost>Hello</mt-button>
            </template>`,
            errors: [{
                message: '[mt-button] The "variant" prop with value "ghost" is deprecated. Please use the "ghost" prop instead.',
            }]
        },
        {
            name: '"mt-button" wrong ghost prop usage [disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: `
            <template>
                <mt-button variant="ghost">Hello</mt-button>
            </template>`,
            errors: [{
                message: '[mt-button] The "variant" prop with value "ghost" is deprecated. Please use the "ghost" prop instead.',
            }]
        },
        {
            name: '"mt-button" wrong danger prop usage in variant',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-button variant="danger">Hello</mt-button>
            </template>`,
            output: `
            <template>
                <mt-button variant="critical">Hello</mt-button>
            </template>`,
            errors: [{
                message: '[mt-button] The "variant" prop with value "danger" is deprecated. Please use the "critical" prop instead.',
            }]
        },
        {
            name: '"mt-button" wrong danger prop usage in variant [disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: `
            <template>
                <mt-button variant="danger">Hello</mt-button>
            </template>`,
            errors: [{
                message: '[mt-button] The "variant" prop with value "danger" is deprecated. Please use the "critical" prop instead.',
            }]
        },
        {
            name: '"mt-button" wrong ghost-danger prop usage in variant',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-button variant="ghost-danger">Hello</mt-button>
            </template>`,
            output: `
            <template>
                <mt-button variant="critical" ghost>Hello</mt-button>
            </template>`,
            errors: [{
                message: '[mt-button] The "variant" prop with value "ghost-danger" is deprecated. Please use the "critical" prop in combination with "ghost" prop instead.',
            }]
        },
        {
            name: '"mt-button" wrong ghost-danger prop usage in variant [disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: `
            <template>
                <mt-button variant="ghost-danger">Hello</mt-button>
            </template>`,
            errors: [{
                message: '[mt-button] The "variant" prop with value "ghost-danger" is deprecated. Please use the "critical" prop in combination with "ghost" prop instead.',
            }]
        },
        {
            name: '"mt-button" wrong contrast prop usage in variant',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-button variant="contrast">Hello</mt-button>
            </template>`,
            output: `
            <template>
                <mt-button variant="TODO-Codemod-Variant-Contrast-Was-Removed">Hello</mt-button>
            </template>`,
            errors: [{
                message: '[mt-button] The "variant" prop with value "contrast" is deprecated without replacement.',
            }]
        },
        {
            name: '"mt-button" wrong contrast prop usage in variant [disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: `
            <template>
                <mt-button variant="contrast">Hello</mt-button>
            </template>`,
            errors: [{
                message: '[mt-button] The "variant" prop with value "contrast" is deprecated without replacement.',
            }]
        },
        {
            name: '"mt-button" wrong contrast prop usage in variant [indented]',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-button
                    variant="contrast"
                >
                    Hello
                </mt-button>
            </template>`,
            output: `
            <template>
                <mt-button
                    variant="TODO-Codemod-Variant-Contrast-Was-Removed"
                >
                    Hello
                </mt-button>
            </template>`,
            errors: [{
                message: '[mt-button] The "variant" prop with value "contrast" is deprecated without replacement.',
            }]
        },
        {
            name: '"mt-button" wrong context prop usage in variant',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-button variant="context">Hello</mt-button>
            </template>`,
            output: `
            <template>
                <mt-button variant="TODO-Codemod-Variant-Context-Was-Removed">Hello</mt-button>
            </template>`,
            errors: [{
                message: '[mt-button] The "variant" prop with value "context" is deprecated without replacement.',
            }]
        },
        {
            name: '"mt-button" wrong context prop usage in variant [disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: `
            <template>
                <mt-button variant="context">Hello</mt-button>
            </template>`,
            errors: [{
                message: '[mt-button] The "variant" prop with value "context" is deprecated without replacement.',
            }]
        },
        {
            name: '"mt-button" deprecated usage of "router-link" prop [string usage]',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-button router-link="sw.example.link">Hello</mt-button>
            </template>`,
            output: `
            <template>
                <mt-button @click="$router.push('sw.example.link')">Hello</mt-button>
            </template>`,
            errors: [{
                message: '[mt-button] The "router-link" prop is deprecated without replacement.',
            }]
        },
        {
            name: '"mt-button" deprecated usage of "router-link" prop [string usage, disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: `
            <template>
                <mt-button router-link="sw.example.link">Hello</mt-button>
            </template>`,
            errors: [{
                message: '[mt-button] The "router-link" prop is deprecated without replacement.',
            }]
        },
        {
            name: '"mt-button" deprecated usage of "router-link" prop [string usage with indents]',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-button
                    router-link="sw.example.link"
                >
                    Hello
                </mt-button>
            </template>`,
            output: `
            <template>
                <mt-button
                    @click="$router.push('sw.example.link')"
                >
                    Hello
                </mt-button>
            </template>`,
            errors: [{
                message: '[mt-button] The "router-link" prop is deprecated without replacement.',
            }]
        },
        {
            name: '"mt-button" deprecated usage of "router-link" prop [bind usage]',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-button :router-link="{ name: 'sw.example.link' }">Hello</mt-button>
            </template>`,
            output: `
            <template>
                <mt-button @click="$router.push({ name: 'sw.example.link' })">Hello</mt-button>
            </template>`,
            errors: [{
                message: '[mt-button] The "router-link" prop is deprecated without replacement.',
            }]
        },
        {
            name: '"mt-button" deprecated usage of "router-link" prop [bind usage, disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: `
            <template>
                <mt-button :router-link="{ name: 'sw.example.link' }">Hello</mt-button>
            </template>`,
            errors: [{
                message: '[mt-button] The "router-link" prop is deprecated without replacement.',
            }]
        },
        {
            name: '"mt-button" deprecated usage of "router-link" prop [bind usage with indents]',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-button
                    :router-link="{ name: 'sw.example.link' }"
                >
                    Hello
                </mt-button>
            </template>`,
            output: `
            <template>
                <mt-button
                    @click="$router.push({ name: 'sw.example.link' })"
                >
                    Hello
                </mt-button>
            </template>`,
            errors: [{
                message: '[mt-button] The "router-link" prop is deprecated without replacement.',
            }]
        },
    ]
})
