const mtBannerValidTests = [
    {
        name: '"sw-alert" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-alert />
            </template>`
    }
];

const mtBannerInvalidTests = [
    {
        name: '"mt-banner" wrong "notificationIndex" prop usage should be replaced with "banner-index"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-banner notificationIndex="1" />
            </template>`,
        output: `
            <template>
                <mt-banner banner-index="1" />
            </template>`,
        errors: [{
            message: '[mt-banner] The "notification-index" API is deprecated. Use "banner-index" instead.',
        }]
    },
    {
        name: '"mt-banner" wrong "appearance" prop usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-banner appearance="foobar" />
            </template>`,
        output: `
            <template>
                <mt-banner  />
            </template>`,
        errors: [{
            message: '[mt-banner] The "appearance" API is deprecated.',
        }]
    },
    {
        name: '"mt-banner" wrong "showIcon" prop usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-banner showIcon />
            </template>`,
        output: `
            <template>
                <mt-banner  />
            </template>`,
        errors: [{
                message: '[mt-banner] The "show-icon" API is deprecated. Use "hide-icon" instead.',
        }]
    },
    {
        name: '"mt-banner" static "showIcon" value that is no boolean is reported for a manual migration',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-banner showIcon="condition" />
            </template>`,
        output: null,
        errors: [{
                message: '[mt-banner] The "show-icon" API is deprecated. Use "hide-icon" instead.\nThe static value "condition" is no boolean. Review it and set "hide-icon" manually.',
        }]
    },
    {
        name: '"mt-banner" static "showIcon" boolean value should be replaced with the inverted bound "hide-icon"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-banner showIcon="false" />
            </template>`,
        output: `
            <template>
                <mt-banner :hide-icon="true" />
            </template>`,
        errors: [{
                message: '[mt-banner] The "show-icon" API is deprecated. Use "hide-icon" instead.',
        }]
    },
    {
        name: '"mt-banner" bound "showIcon" condition should be replaced with the negated "hide-icon"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-banner :showIcon="condition" />
            </template>`,
        output: `
            <template>
                <mt-banner :hide-icon="!(condition)" />
            </template>`,
        errors: [{
                message: '[mt-banner] The "show-icon" API is deprecated. Use "hide-icon" instead.',
        }]
    },
    {
        name: '"mt-banner" wrong "variant" prop usage with value "warning" should be replaced with "attention"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-banner variant="warning" />
            </template>`,
        output: `
            <template>
                <mt-banner variant="attention" />
            </template>`,
        errors: [{
            message: '[mt-banner] The "variant" API is deprecated. Use "attention" instead.',
        }]
    },
    {
        name: '"mt-banner" wrong "variant" prop usage with value "error" should be replaced with "critical"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-banner variant="error" />
            </template>`,
        output: `
            <template>
                <mt-banner variant="critical" />
            </template>`,
        errors: [{
            message: '[mt-banner] The "variant" API is deprecated. Use "critical" instead.',
        }]
    },
    {
        name: '"mt-banner" wrong "variant" prop usage with value "success" should be replaced with "positive"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-banner variant="success" />
            </template>`,
        output: `
            <template>
                <mt-banner variant="positive" />
            </template>`,
        errors: [{
            message: '[mt-banner] The "variant" API is deprecated. Use "positive" instead.',
        }]
    },
    {
        name: '"mt-banner" deprecated "actions" slot usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-banner>
                    <template #actions>
                        <sw-button />
                    </template>
                </mt-banner>
            </template>`,
        output: `
            <template>
                <mt-banner>
                    <!-- Slot "actions" was removed and has no replacement. -->
                </mt-banner>
            </template>`,
        errors: [{
            message: '[mt-banner] The "actions" API is deprecated.',
        }]
    },
    {
        name: '"mt-banner" deprecated "actions" slot usage should be removed [v-slot syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-banner>
                    <template v-slot:actions>
                        <sw-button />
                    </template>
                </mt-banner>
            </template>`,
        output: `
            <template>
                <mt-banner>
                    <!-- Slot "actions" was removed and has no replacement. -->
                </mt-banner>
            </template>`,
        errors: [{
            message: '[mt-banner] The "actions" API is deprecated.',
        }]
    },
];

module.exports = {
    mtBannerValidTests,
    mtBannerInvalidTests
};