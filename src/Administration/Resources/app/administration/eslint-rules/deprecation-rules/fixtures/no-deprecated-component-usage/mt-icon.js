const mtIconValidTests = [
    {
        name: '"sw-icon" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-icon  name="regular-times-s"/>
            </template>`,
    },
    {
        name: '"mt-icon" without a size prop keeps the mt-icon default size',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-icon name="regular-times-s" />
            </template>`,
    },
    {
        name: '"mt-icon" without a size prop is not changed once the "Converted from sw-icon" comment is resolved',
        filename: 'test.html.twig',
        code: `
            <template>
                <!-- Close button -->
                <mt-icon name="regular-times-s" />
            </template>`,
    },
];

const mtIconInvalidTests = [
    {
        name: '"mt-icon" wrong "small" prop usage should be replaced with size prop with value 16px',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-icon name="regular-times-s" small />
            </template>`,
        output: `
            <template>
                <mt-icon name="regular-times-s" size="16px" />
            </template>`,
        errors: [
            {
                message: '[mt-icon] The "small" API is deprecated. Use "size" instead.',
            },
        ],
    },
    {
        name: '"mt-icon" wrong "small" prop usage should be replaced with size prop with value 16px [nested]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-icon
                    name="regular-times-s"
                    small
                />
            </template>`,
        output: `
            <template>
                <mt-icon
                    name="regular-times-s"
                    size="16px"
                />
            </template>`,
        errors: [
            {
                message: '[mt-icon] The "small" API is deprecated. Use "size" instead.',
            },
        ],
    },
    {
        name: '"mt-icon" wrong "small" prop usage should be removed when size prop already exists',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-icon name="regular-times-s" small size="32px" />
            </template>`,
        output: `
            <template>
                <mt-icon name="regular-times-s"  size="32px" />
            </template>`,
        errors: [
            {
                message: '[mt-icon] The "small" API is deprecated. Use "size" instead.',
            },
        ],
    },
    {
        name: '"mt-icon" wrong "small" and "large" prop usage should not be fixed automatically',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-icon name="regular-times-s" small large />
            </template>`,
        output: null,
        errors: [
            {
                message: '[mt-icon] The "small" API is deprecated. Use "size" instead.',
            },
            {
                message: '[mt-icon] The "large" API is deprecated. Use "size" instead.',
            },
        ],
    },
    {
        name: '"mt-icon" wrong "small" prop usage should be removed when size prop already exists [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-icon name="regular-times-s" small size="32px" />
            </template>`,
        errors: [
            {
                message: '[mt-icon] The "small" API is deprecated. Use "size" instead.',
            },
        ],
    },
    {
        name: '"mt-icon" wrong "small" prop usage should be replaced with size prop with value 16px [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-icon name="regular-times-s" small />
            </template>`,
        errors: [
            {
                message: '[mt-icon] The "small" API is deprecated. Use "size" instead.',
            },
        ],
    },
    {
        name: '"mt-icon" wrong "small" prop with value inside (e.g. "true") usage should be replaced with size prop with value 16px',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-icon name="regular-times-s" :small="true" />
            </template>`,
        output: `
            <template>
                <mt-icon name="regular-times-s" size="16px" />
            </template>`,
        errors: [
            {
                message: '[mt-icon] The "small" API is deprecated. Use "size" instead.',
            },
        ],
    },
    {
        name: '"mt-icon" wrong "small" prop with value inside (e.g. "true") usage should be replaced with size prop with value 16px [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-icon name="regular-times-s" :small="true" />
            </template>`,
        errors: [
            {
                message: '[mt-icon] The "small" API is deprecated. Use "size" instead.',
            },
        ],
    },
    {
        name: '"mt-icon" wrong dynamic "small" prop expression should be reported without auto-fix',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-icon name="regular-times-s" :small="isSmall" />
            </template>`,
        output: null,
        errors: [
            {
                message:
                    '[mt-icon] The "small" API is deprecated. Use "size" instead.\nExpression-bound prop values can be false at runtime. Review the expression and replace it with "16px" manually if needed.',
            },
        ],
    },
    {
        name: '"mt-icon" wrong "small" prop with value inside (e.g. "true") usage should be removed when size prop already exists',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-icon name="regular-times-s" :small="true" size="42px" />
            </template>`,
        output: `
            <template>
                <mt-icon name="regular-times-s"  size="42px" />
            </template>`,
        errors: [
            {
                message: '[mt-icon] The "small" API is deprecated. Use "size" instead.',
            },
        ],
    },
    {
        name: '"mt-icon" wrong "small" and "large" expression prop usage should not be fixed automatically',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-icon name="regular-times-s" :small="true" :large="true" />
            </template>`,
        output: null,
        errors: [
            {
                message: '[mt-icon] The "small" API is deprecated. Use "size" instead.',
            },
            {
                message: '[mt-icon] The "large" API is deprecated. Use "size" instead.',
            },
        ],
    },
    {
        name: '"mt-icon" wrong "small" prop with value inside (e.g. "true") usage should be removed when size prop already exists [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-icon name="regular-times-s" :small="true" size="42px" />
            </template>`,
        errors: [
            {
                message: '[mt-icon] The "small" API is deprecated. Use "size" instead.',
            },
        ],
    },
    {
        name: '"mt-icon" wrong "large" prop usage should be replaced with size prop with value 32px',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-icon name="regular-times-s" large />
            </template>`,
        output: `
            <template>
                <mt-icon name="regular-times-s" size="32px" />
            </template>`,
        errors: [
            {
                message: '[mt-icon] The "large" API is deprecated. Use "size" instead.',
            },
        ],
    },
    {
        name: '"mt-icon" wrong "large" prop usage should be replaced with size prop with value 32px [nested]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-icon
                    name="regular-times-s"
                    large
                />
            </template>`,
        output: `
            <template>
                <mt-icon
                    name="regular-times-s"
                    size="32px"
                />
            </template>`,
        errors: [
            {
                message: '[mt-icon] The "large" API is deprecated. Use "size" instead.',
            },
        ],
    },
    {
        name: '"mt-icon" wrong "large" prop usage should be removed when size prop already exists',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-icon name="regular-times-s" large size="42px" />
            </template>`,
        output: `
            <template>
                <mt-icon name="regular-times-s"  size="42px" />
            </template>`,
        errors: [
            {
                message: '[mt-icon] The "large" API is deprecated. Use "size" instead.',
            },
        ],
    },
    {
        name: '"mt-icon" wrong "large" prop usage should be removed when size prop already exists [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-icon name="regular-times-s" large size="42px" />
            </template>`,
        errors: [
            {
                message: '[mt-icon] The "large" API is deprecated. Use "size" instead.',
            },
        ],
    },
    {
        name: '"mt-icon" wrong "large" prop usage should be replaced with size prop with value 32px [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-icon name="regular-times-s" large />
            </template>`,
        errors: [
            {
                message: '[mt-icon] The "large" API is deprecated. Use "size" instead.',
            },
        ],
    },
    {
        name: '"mt-icon" wrong "large" prop with value inside (e.g. "true") usage should be replaced with size prop with value 32px',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-icon name="regular-times-s" :large="true" />
            </template>`,
        output: `
            <template>
                <mt-icon name="regular-times-s" size="32px" />
            </template>`,
        errors: [
            {
                message: '[mt-icon] The "large" API is deprecated. Use "size" instead.',
            },
        ],
    },
    {
        name: '"mt-icon" wrong "large" prop with value inside (e.g. "true") usage should be replaced with size prop with value 32px [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-icon name="regular-times-s" :large="true" />
            </template>`,
        errors: [
            {
                message: '[mt-icon] The "large" API is deprecated. Use "size" instead.',
            },
        ],
    },
    {
        name: '"mt-icon" wrong "large" prop with value inside (e.g. "true") usage should be removed when size prop already exists',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-icon name="regular-times-s" :large="true" size="42px" />
            </template>`,
        output: `
            <template>
                <mt-icon name="regular-times-s"  size="42px" />
            </template>`,
        errors: [
            {
                message: '[mt-icon] The "large" API is deprecated. Use "size" instead.',
            },
        ],
    },
    {
        name: '"mt-icon" wrong "large" prop with value inside (e.g. "true") usage should be removed when size prop already exists [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-icon name="regular-times-s" :large="true" size="42px" />
            </template>`,
        errors: [
            {
                message: '[mt-icon] The "large" API is deprecated. Use "size" instead.',
            },
        ],
    },
    {
        name: '"mt-icon" converted from "sw-icon" without a size prop gets size 24px and a review hint',
        filename: 'test.html.twig',
        code: `
            <template>
                <!-- TODO Codemod: Converted from sw-icon - please check if everything works correctly -->
                <mt-icon name="regular-times-s" />
            </template>`,
        output: `
            <template>
                <!-- TODO Codemod: Converted from sw-icon - please check if everything works correctly -->
                <!-- TODO Codemod: size="24px" keeps the sw-icon default size. Keep it, or remove it to use the mt-icon default size, and remove the "Converted from sw-icon" comment so the size is not added again. -->
                <mt-icon name="regular-times-s" size="24px" />
            </template>`,
        errors: [
            {
                message: '[mt-icon] Add size="24px" when no size is set to preserve the previous default icon size.',
            },
        ],
    },
    {
        name: '"mt-icon" converted from "sw-icon" without a size prop gets size 24px [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <!-- TODO Codemod: Converted from sw-icon - please check if everything works correctly -->
                <mt-icon name="regular-times-s" />
            </template>`,
        errors: [
            {
                message: '[mt-icon] Add size="24px" when no size is set to preserve the previous default icon size.',
            },
        ],
    },
    {
        name: '"mt-icon" converted from "sw-icon" without a size prop gets size 24px [nested]',
        filename: 'test.html.twig',
        code: `
            <template>
                <!-- TODO Codemod: Converted from sw-icon - please check if everything works correctly -->
                <mt-icon
                    name="regular-times-s"
                />
            </template>`,
        output: `
            <template>
                <!-- TODO Codemod: Converted from sw-icon - please check if everything works correctly -->
                <!-- TODO Codemod: size="24px" keeps the sw-icon default size. Keep it, or remove it to use the mt-icon default size, and remove the "Converted from sw-icon" comment so the size is not added again. -->
                <mt-icon
                    name="regular-times-s" size="24px"
                />
            </template>`,
        errors: [
            {
                message: '[mt-icon] Add size="24px" when no size is set to preserve the previous default icon size.',
            },
        ],
    },
    {
        name: '"mt-icon" converted from "sw-icon" without any attribute gets size 24px',
        filename: 'test.html.twig',
        code: `
            <template>
                <!-- TODO Codemod: Converted from sw-icon - please check if everything works correctly -->
                <mt-icon />
            </template>`,
        output: `
            <template>
                <!-- TODO Codemod: Converted from sw-icon - please check if everything works correctly -->
                <!-- TODO Codemod: size="24px" keeps the sw-icon default size. Keep it, or remove it to use the mt-icon default size, and remove the "Converted from sw-icon" comment so the size is not added again. -->
                <mt-icon size="24px" />
            </template>`,
        errors: [
            {
                message: '[mt-icon] Add size="24px" when no size is set to preserve the previous default icon size.',
            },
        ],
    },
];

module.exports = {
    valid: mtIconValidTests,
    invalid: mtIconInvalidTests,
};
