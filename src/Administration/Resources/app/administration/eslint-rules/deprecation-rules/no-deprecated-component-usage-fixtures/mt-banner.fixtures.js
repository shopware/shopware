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
            message: '[mt-banner] The "notificationIndex" prop is deprecated. Use "banner-index" instead.',
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
            message: '[mt-banner] The "appearance" prop is deprecated. Remove it.',
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
                message: '[mt-banner] The "showIcon" prop is deprecated. Use "hideIcon" instead.',
        }]
    },
    {
        name: '"mt-banner" wrong "showIcon" prop usage with condition should be replaced with "hideIcon"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-banner showIcon="condition" />
            </template>`,
        output: `
            <template>
                <mt-banner hide-icon="!(condition)" />
            </template>`,
        errors: [{
                message: '[mt-banner] The "showIcon" prop is deprecated. Use "hideIcon" instead.',
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
            message: '[mt-banner] The value "warning" for prop "variant" is deprecated. Use "attention" instead.',
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
            message: '[mt-banner] The value "error" for prop "variant" is deprecated. Use "critical" instead.',
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
            message: '[mt-banner] The value "success" for prop "variant" is deprecated. Use "positive" instead.',
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
            message: '[mt-banner] The "actions" slot is deprecated. Remove it.',
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
            message: '[mt-banner] The "actions" slot is deprecated. Remove it.',
        }]
    },
];

module.exports = {
    mtBannerValidTests,
    mtBannerInvalidTests
};