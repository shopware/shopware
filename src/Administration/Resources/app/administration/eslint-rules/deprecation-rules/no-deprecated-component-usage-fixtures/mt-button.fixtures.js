const mtButtonValidChecks = [
    {
        name: '"mt-button" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-button variant="primary">Hello</mt-button>
            </template>`,
    },
    {
        name: '"sw-button" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-button>Hello</sw-button>
            </template>`,
    },
    {
        name: '"mt-button" new ghost prop usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-button variant="primary" ghost>Hello</mt-button>
            </template>`,
    },
    {
        name: 'Ignore wrong "sw-button" usage with old variant prop "ghost"',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-button variant="ghost">Hello</sw-button>
            </template>`,
    },
    {
        name: '"mt-button" variant shouldn\'t be replaced with "secondary" when it is binded',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-button :variant="dynamicVariant">Hello</mt-button>
            </template>`,
    },
];
const mtButtonInvalidChecks = [
    {
        name: '"mt-button" wrong ghost prop usage',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-button variant="ghost">Hello</mt-button>
            </template>`,
        output: `
            <template>
                <mt-button variant="primary" ghost>Hello</mt-button>
            </template>`,
        errors: [
            {
                message:
                    '[mt-button] The "variant" prop with value "ghost" is deprecated. Please use the "primary" prop in combination with "ghost" prop instead.',
            },
        ],
    },
    {
        name: '"mt-button" wrong ghost prop usage [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-button variant="ghost">Hello</mt-button>
            </template>`,
        errors: [
            {
                message:
                    '[mt-button] The "variant" prop with value "ghost" is deprecated. Please use the "primary" prop in combination with "ghost" prop instead.',
            },
        ],
    },
    {
        name: '"mt-button" wrong ghost prop usage does not duplicate existing ghost prop',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-button variant="ghost" ghost>Hello</mt-button>
            </template>`,
        output: `
            <template>
                <mt-button variant="primary" ghost>Hello</mt-button>
            </template>`,
        errors: [
            {
                message:
                    '[mt-button] The "variant" prop with value "ghost" is deprecated. Please use the "primary" prop in combination with "ghost" prop instead.',
            },
        ],
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
        errors: [
            {
                message:
                    '[mt-button] The "variant" prop with value "danger" is deprecated. Please use the "critical" prop instead.',
            },
        ],
    },
    {
        name: '"mt-button" wrong danger prop usage in variant [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-button variant="danger">Hello</mt-button>
            </template>`,
        errors: [
            {
                message:
                    '[mt-button] The "variant" prop with value "danger" is deprecated. Please use the "critical" prop instead.',
            },
        ],
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
        errors: [
            {
                message:
                    '[mt-button] The "variant" prop with value "ghost-danger" is deprecated. Please use the "critical" prop in combination with "ghost" prop instead.',
            },
        ],
    },
    {
        name: '"mt-button" wrong ghost-danger prop usage in variant [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-button variant="ghost-danger">Hello</mt-button>
            </template>`,
        errors: [
            {
                message:
                    '[mt-button] The "variant" prop with value "ghost-danger" is deprecated. Please use the "critical" prop in combination with "ghost" prop instead.',
            },
        ],
    },
    {
        name: '"mt-button" wrong ghost-danger prop usage does not duplicate existing ghost prop',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-button variant="ghost-danger" ghost>Hello</mt-button>
            </template>`,
        output: `
            <template>
                <mt-button variant="critical" ghost>Hello</mt-button>
            </template>`,
        errors: [
            {
                message:
                    '[mt-button] The "variant" prop with value "ghost-danger" is deprecated. Please use the "critical" prop in combination with "ghost" prop instead.',
            },
        ],
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
        errors: [
            {
                message: '[mt-button] The "variant" prop with value "contrast" is deprecated without replacement.',
            },
        ],
    },
    {
        name: '"mt-button" wrong contrast prop usage in variant [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-button variant="contrast">Hello</mt-button>
            </template>`,
        errors: [
            {
                message: '[mt-button] The "variant" prop with value "contrast" is deprecated without replacement.',
            },
        ],
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
        errors: [
            {
                message: '[mt-button] The "variant" prop with value "contrast" is deprecated without replacement.',
            },
        ],
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
        errors: [
            {
                message: '[mt-button] The "variant" prop with value "context" is deprecated without replacement.',
            },
        ],
    },
    {
        name: '"mt-button" wrong context prop usage in variant [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-button variant="context">Hello</mt-button>
            </template>`,
        errors: [
            {
                message: '[mt-button] The "variant" prop with value "context" is deprecated without replacement.',
            },
        ],
    },
    {
        name: '"mt-button" no variant defined will be replaced with secondary',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-button>Hello</mt-button>
            </template>`,
        output: `
            <template>
                <mt-button variant="secondary">Hello</mt-button>
            </template>`,
        errors: [
            {
                message: '[mt-button] No variant defined. Please use the "secondary" prop instead.',
            },
        ],
    },
    {
        name: '"mt-button" no variant defined is inserted before object v-bind',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-button v-bind="buttonProps">Hello</mt-button>
            </template>`,
        output: `
            <template>
                <mt-button variant="secondary" v-bind="buttonProps">Hello</mt-button>
            </template>`,
        errors: [
            {
                message: '[mt-button] No variant defined. Please use the "secondary" prop instead.',
            },
        ],
    },
    {
        name: '"mt-button" no variant defined will be replaced with secondary [With more props]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-button
                    v-tooltip.bottom="tooltipCancel"
                    :disabled="isLoading"
                    @click="onCancel"
                >
                    {{ $tc('global.default.cancel') }}
                </mt-button>
            </template>`,
        output: `
            <template>
                <mt-button
                    v-tooltip.bottom="tooltipCancel"
                    :disabled="isLoading"
                    @click="onCancel"
                 variant="secondary">
                    {{ $tc('global.default.cancel') }}
                </mt-button>
            </template>`,
        errors: [
            {
                message: '[mt-button] No variant defined. Please use the "secondary" prop instead.',
            },
        ],
    },
    {
        name: '"mt-button" deprecated usage of "router-link" prop [string usage]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-button variant="secondary" router-link="sw.example.link">Hello</mt-button>
            </template>`,
        output: `
            <template>
                <mt-button variant="secondary" @click="$router.push('sw.example.link')">Hello</mt-button>
            </template>`,
        errors: [
            {
                message: '[mt-button] The "router-link" prop is deprecated without replacement.',
            },
        ],
    },
    {
        name: '"mt-button" deprecated usage of "router-link" prop [string usage, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-button variant="secondary" router-link="sw.example.link">Hello</mt-button>
            </template>`,
        errors: [
            {
                message: '[mt-button] The "router-link" prop is deprecated without replacement.',
            },
        ],
    },
    {
        name: '"mt-button" deprecated usage of "router-link" prop [string usage with indents]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-button
                    router-link="sw.example.link"
                    variant="secondary"
                >
                    Hello
                </mt-button>
            </template>`,
        output: `
            <template>
                <mt-button
                    @click="$router.push('sw.example.link')"
                    variant="secondary"
                >
                    Hello
                </mt-button>
            </template>`,
        errors: [
            {
                message: '[mt-button] The "router-link" prop is deprecated without replacement.',
            },
        ],
    },
    {
        name: '"mt-button" deprecated usage of "router-link" prop [bind usage]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-button variant="secondary" :router-link="{ name: 'sw.example.link' }">Hello</mt-button>
            </template>`,
        output: `
            <template>
                <mt-button variant="secondary" @click="$router.push({ name: 'sw.example.link' })">Hello</mt-button>
            </template>`,
        errors: [
            {
                message: '[mt-button] The "router-link" prop is deprecated without replacement.',
            },
        ],
    },
    {
        name: '"mt-button" deprecated usage of "router-link" prop [bind usage, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-button variant="secondary" :router-link="{ name: 'sw.example.link' }">Hello</mt-button>
            </template>`,
        errors: [
            {
                message: '[mt-button] The "router-link" prop is deprecated without replacement.',
            },
        ],
    },
    {
        name: '"mt-button" deprecated usage of "router-link" prop [bind usage with indents]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-button
                    :router-link="{ name: 'sw.example.link' }"
                    variant="secondary"
                >
                    Hello
                </mt-button>
            </template>`,
        output: `
            <template>
                <mt-button
                    @click="$router.push({ name: 'sw.example.link' })"
                    variant="secondary"
                >
                    Hello
                </mt-button>
            </template>`,
        errors: [
            {
                message: '[mt-button] The "router-link" prop is deprecated without replacement.',
            },
        ],
    },
    {
        name: '"mt-button" deprecated usage of "router-link" prop should not duplicate existing click handler',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-button variant="secondary" @click="onClick" router-link="sw.example.link">Hello</mt-button>
            </template>`,
        output: null,
        errors: [
            {
                message: '[mt-button] The "router-link" prop is deprecated without replacement.',
            },
        ],
    },
];

module.exports = {
    mtButtonValidChecks,
    mtButtonInvalidChecks
};
