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
        name: '"mt-floating-ui" set "is-opened" prop to "true" when not exists to maintain backward compatibility',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-floating-ui :is-opened="true" />
            </template>`,
    },
    {
        name: '"mt-floating-ui" set "is-opened" prop to "true" when not exists to maintain backward compatibility',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-floating-ui is-opened="true" />
            </template>`,
    },
    {
        name: '"mt-floating-ui" set "is-opened" prop to "true" when not exists to maintain backward compatibility',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-floating-ui is-opened />
            </template>`,
    },
];

const mtFloatingUiInvalidTests = [
    {
        name: '"mt-floating-ui" set "is-opened" prop to "true" when not exists to maintain backward compatibility',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-floating-ui />
            </template>`,
        output: `
            <template>
                <mt-floating-ui :is-opened="true" />
            </template>`,
        errors: [
            {
                message:
                    '[mt-floating-ui] Add :is-opened="true" when no opened state is set to preserve the previous default visibility.',
            },
        ],
    },
    {
        name: '"mt-floating-ui" set "is-opened" prop to "true" when not exists to maintain backward compatibility',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-floating-ui />
            </template>`,
        errors: [
            {
                message:
                    '[mt-floating-ui] Add :is-opened="true" when no opened state is set to preserve the previous default visibility.',
            },
        ],
    },
    {
        name: '"mt-floating-ui" does not add is-opened when legacy open prop still needs manual migration',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-floating-ui open />
            </template>`,
        output: null,
        errors: [
            {
                message: '[mt-floating-ui] The "open" API is deprecated. Use "is-opened" instead.',
            },
        ],
    },
    {
        name: '"mt-floating-ui" does not add is-opened when legacy bound open prop still needs manual migration',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-floating-ui :open="false" />
            </template>`,
        output: null,
        errors: [
            {
                message: '[mt-floating-ui] The "open" API is deprecated. Use "is-opened" instead.',
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
                <mt-floating-ui :is-opened="true" match-reference-width />
            </template>`,
        errors: [
            {
                message: '[mt-floating-ui] Add :is-opened="true" when no opened state is set to preserve the previous default visibility.',
            },
            {
                message:
                    '[mt-floating-ui] The "resize-width" API is deprecated. Use "match-reference-width" instead.',
            },
        ],
    },
    {
        name: '"mt-floating-ui" removes resize-width when match-reference-width already passes the same value',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-floating-ui match-reference-width resize-width :is-opened="true" />
            </template>`,
        output: `
            <template>
                <mt-floating-ui match-reference-width :is-opened="true" />
            </template>`,
        errors: [
            {
                message:
                    '[mt-floating-ui] The "resize-width" API is deprecated. Use "match-reference-width" instead.\nThe element already passes "match-reference-width" with the same value, so the deprecated attribute is removed.',
            },
        ],
    },
    {
        name: '"mt-floating-ui" does not fix resize-width expression when match-reference-width already exists',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-floating-ui :match-reference-width="foo" :resize-width="bar" :is-opened="true" />
            </template>`,
        output: null,
        errors: [
            {
                message: '[mt-floating-ui] The "resize-width" API is deprecated. Use "match-reference-width" instead.',
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
                <mt-floating-ui :is-opened="true" />
            </template>`,
        errors: [
            {
                message: '[mt-floating-ui] Add :is-opened="true" when no opened state is set to preserve the previous default visibility.',
            },
            {
                message:
                    '[mt-floating-ui] The "popover-class" API is deprecated.',
            },
        ],
    },
];

module.exports = {
    valid: mtFloatingUiValidTests,
    invalid: mtFloatingUiInvalidTests,
};
