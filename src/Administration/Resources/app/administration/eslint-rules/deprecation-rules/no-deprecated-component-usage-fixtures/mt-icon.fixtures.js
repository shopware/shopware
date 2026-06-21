const mtIconValidTests = [
    {
        name: '"sw-icon" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-icon  name="regular-times-s"/>
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
                message: '[mt-icon] The "small" prop is deprecated. Please use the "size" prop with value "16px" instead.',
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
                message: '[mt-icon] The "small" prop is deprecated. Please use the "size" prop with value "16px" instead.',
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
                message: '[mt-icon] The "small" prop is deprecated. Please use the "size" prop with value "16px" instead.',
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
                message: '[mt-icon] The "small" prop is deprecated. Please use the "size" prop with value "16px" instead.',
            },
            {
                message: '[mt-icon] The "large" prop is deprecated. Please use the "size" prop with value "32px" instead.',
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
                message: '[mt-icon] The "small" prop is deprecated. Please use the "size" prop with value "16px" instead.',
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
                message: '[mt-icon] The "small" prop is deprecated. Please use the "size" prop with value "16px" instead.',
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
                message: '[mt-icon] The "small" prop is deprecated. Please use the "size" prop with value "16px" instead.',
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
                message: '[mt-icon] The "small" prop is deprecated. Please use the "size" prop with value "16px" instead.',
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
                message: '[mt-icon] The "small" prop is deprecated. Please use the "size" prop with value "16px" instead.',
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
                message: '[mt-icon] The "small" prop is deprecated. Please use the "size" prop with value "16px" instead.',
            },
            {
                message: '[mt-icon] The "large" prop is deprecated. Please use the "size" prop with value "32px" instead.',
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
                message: '[mt-icon] The "small" prop is deprecated. Please use the "size" prop with value "16px" instead.',
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
                message: '[mt-icon] The "large" prop is deprecated. Please use the "size" prop with value "32px" instead.',
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
                message: '[mt-icon] The "large" prop is deprecated. Please use the "size" prop with value "32px" instead.',
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
                message: '[mt-icon] The "large" prop is deprecated. Please use the "size" prop with value "32px" instead.',
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
                message: '[mt-icon] The "large" prop is deprecated. Please use the "size" prop with value "32px" instead.',
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
                message: '[mt-icon] The "large" prop is deprecated. Please use the "size" prop with value "32px" instead.',
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
                message: '[mt-icon] The "large" prop is deprecated. Please use the "size" prop with value "32px" instead.',
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
                message: '[mt-icon] The "large" prop is deprecated. Please use the "size" prop with value "32px" instead.',
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
                message: '[mt-icon] The "large" prop is deprecated. Please use the "size" prop with value "32px" instead.',
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
                message: '[mt-icon] The "large" prop is deprecated. Please use the "size" prop with value "32px" instead.',
            },
        ],
    },
    // {
    //     name: '"mt-icon" without a size prop should be replaced with size prop with value 24px',
    //     filename: 'test.html.twig',
    //     code: `
    //         <template>
    //             <mt-icon name="regular-times-s" />
    //         </template>`,
    //     output: `
    //         <template>
    //             <mt-icon name="regular-times-s" size="24px" />
    //         </template>`,
    //     errors: [{
    //         message: '[mt-icon] The size of the icon is not 24px by default now. Please use the "size" prop with value "24px" to set the size explicitly if needed.',
    //     }]
    // },
    // {
    //     name: '"mt-icon" without a size prop should be replaced with size prop with value 24px [disableFix]',
    //     filename: 'test.html.twig',
    //     options: ['disableFix'],
    //     code: `
    //         <template>
    //             <mt-icon name="regular-times-s" />
    //         </template>`,
    //     errors: [{
    //         message: '[mt-icon] The size of the icon is not 24px by default now. Please use the "size" prop with value "24px" to set the size explicitly if needed.',
    //     }]
    // },
    // {
    //     name: '"mt-icon" without a size prop should be replaced with size prop with value 24px [nested]',
    //     filename: 'test.html.twig',
    //     code: `
    //         <template>
    //             <mt-icon
    //                 name="regular-times-s"
    //             />
    //         </template>`,
    //     output: `
    //         <template>
    //             <mt-icon
    //                 name="regular-times-s" size="24px"
    //             />
    //         </template>`,
    //     errors: [{
    //         message: '[mt-icon] The size of the icon is not 24px by default now. Please use the "size" prop with value "24px" to set the size explicitly if needed.',
    //     }]
    // },
    // {
    //     name: '"mt-icon" without a size prop should be replaced with size prop with value 24px [nested, disableFix]',
    //     filename: 'test.html.twig',
    //     options: ['disableFix'],
    //     code: `
    //         <template>
    //             <mt-icon
    //                 name="regular-times-s"
    //             />
    //         </template>`,
    //     errors: [{
    //         message: '[mt-icon] The size of the icon is not 24px by default now. Please use the "size" prop with value "24px" to set the size explicitly if needed.',
    //     }]
    // },
    // {
    //     name: '"mt-icon" without a size prop should be replaced with size prop with value 24px [no other attributes]',
    //     filename: 'test.html.twig',
    //     code: `
    //         <template>
    //             <mt-icon />
    //         </template>`,
    //     output: `
    //         <template>
    //             <mt-icon size="24px" />
    //         </template>`,
    //     errors: [{
    //         message: '[mt-icon] The size of the icon is not 24px by default now. Please use the "size" prop with value "24px" to set the size explicitly if needed.',
    //     }]
    // },
    // {
    //     name: '"mt-icon" without a size prop should be replaced with size prop with value 24px [no other attributes, disableFix]',
    //     filename: 'test.html.twig',
    //     options: ['disableFix'],
    //     code: `
    //         <template>
    //             <mt-icon />
    //         </template>`,
    //     errors: [{
    //         message: '[mt-icon] The size of the icon is not 24px by default now. Please use the "size" prop with value "24px" to set the size explicitly if needed.',
    //     }]
    // },
];

module.exports = {
    mtIconValidTests,
    mtIconInvalidTests
};