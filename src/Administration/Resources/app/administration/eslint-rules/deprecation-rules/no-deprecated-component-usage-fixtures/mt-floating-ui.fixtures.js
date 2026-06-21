const mtFloatingUiValidTests = [
    {
        name: '"sw-popover" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-popover />
            </template>`,
    },
    {
        name: '"mt-floating-ui" set "isOpened" prop to "true" when not exists to maintain backward compatibility',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-floating-ui :isOpened="true" />
            </template>`,
    },
    {
        name: '"mt-floating-ui" set "isOpened" prop to "true" when not exists to maintain backward compatibility',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-floating-ui isOpened="true" />
            </template>`,
    },
    {
        name: '"mt-floating-ui" set "isOpened" prop to "true" when not exists to maintain backward compatibility',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-floating-ui isOpened />
            </template>`,
    },
];

const mtFloatingUiInvalidTests = [
    {
        name: '"mt-floating-ui" set "isOpened" prop to "true" when not exists to maintain backward compatibility',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-floating-ui />
            </template>`,
        output: `
            <template>
                <mt-floating-ui :isOpened="true" />
            </template>`,
        errors: [
            {
                message:
                    '[mt-floating-ui] The floating-ui is not opened by default. Please set the "isOpened" prop to "true" to maintain backward compatibility.',
            },
        ],
    },
    {
        name: '"mt-floating-ui" set "isOpened" prop to "true" when not exists to maintain backward compatibility',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-floating-ui />
            </template>`,
        errors: [
            {
                message:
                    '[mt-floating-ui] The floating-ui is not opened by default. Please set the "isOpened" prop to "true" to maintain backward compatibility.',
            },
        ],
    },
    {
        name: '"mt-floating-ui" replaces resize-width with match-reference-width',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-floating-ui resize-width />
            </template>`,
        output: `
            <template>
                <mt-floating-ui :isOpened="true" match-reference-width />
            </template>`,
        errors: [
            {
                message: '[mt-floating-ui] The "resize-width" prop is deprecated. Use "match-reference-width" instead.',
            },
            {
                message:
                    '[mt-floating-ui] The floating-ui is not opened by default. Please set the "isOpened" prop to "true" to maintain backward compatibility.',
            },
        ],
    },
    {
        name: '"mt-floating-ui" does not fix resize-width when match-reference-width already exists',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-floating-ui match-reference-width resize-width :isOpened="true" />
            </template>`,
        output: null,
        errors: [
            {
                message: '[mt-floating-ui] The "resize-width" prop is deprecated. Use "match-reference-width" instead.',
            },
        ],
    },
    {
        name: '"mt-floating-ui" does not fix resize-width expression when match-reference-width already exists',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-floating-ui :match-reference-width="foo" :resize-width="bar" :isOpened="true" />
            </template>`,
        output: null,
        errors: [
            {
                message: '[mt-floating-ui] The "resize-width" prop is deprecated. Use "match-reference-width" instead.',
            },
        ],
    },
    {
        name: '"mt-floating-ui" removes popover-class',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-floating-ui popover-class="legacy" />
            </template>`,
        output: `
            <template>
                <mt-floating-ui :isOpened="true"  />
            </template>`,
        errors: [
            {
                message: '[mt-floating-ui] The "popover-class" prop is deprecated. Remove it.',
            },
            {
                message:
                    '[mt-floating-ui] The floating-ui is not opened by default. Please set the "isOpened" prop to "true" to maintain backward compatibility.',
            },
        ],
    },
];

module.exports = {
    mtFloatingUiValidTests,
    mtFloatingUiInvalidTests
};