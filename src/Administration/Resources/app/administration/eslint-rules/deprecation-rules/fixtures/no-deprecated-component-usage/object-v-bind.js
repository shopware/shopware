// An object v-bind can hide any prop, so prop fixes fall back to a manual review.
const objectVBindInvalidTests = [
    {
        name: 'does not crash on object v-bind while checking deprecated button variant values',
        filename: 'test.html.twig',
        code: '<template><mt-button v-bind="buttonProps" variant="ghost" /></template>',
        output: null,
        errors: [
            {
                message: '[mt-button] The "variant" API is deprecated. Use "primary" instead.',
            },
        ],
    },
    {
        name: 'does not fix router-link when object v-bind can hide button props',
        filename: 'test.html.twig',
        code: '<template><mt-button v-bind="buttonProps" variant="primary" router-link="sw.dashboard.index" /></template>',
        output: null,
        errors: [
            {
                message:
                    '[mt-button] Replace router-link with an explicit click handler or router-link wrapper and verify navigation semantics.\nObject v-bind can hide router-link usage. Review the bound object and migrate navigation manually.',
            },
        ],
    },
    {
        name: 'does not fix ai-badge when object v-bind can hide card props',
        filename: 'test.html.twig',
        code: '<template><mt-card v-bind="cardProps" ai-badge /></template>',
        output: null,
        errors: [
            {
                message:
                    '[mt-card] Remove the AI badge prop and render the AI badge directly in the title slot.\nObject v-bind can hide ai-badge usage. Review the bound object and add the title slot manually if needed.',
            },
        ],
    },
    {
        name: 'does not fix removed props when object v-bind can hide card props',
        filename: 'test.html.twig',
        code: '<template><mt-card v-bind="cardProps" content-padding /></template>',
        output: null,
        errors: [
            {
                message: '[mt-card] The "content-padding" API is deprecated.',
            },
        ],
    },
    {
        name: 'does not fix renamed props when object v-bind can hide replacement props',
        filename: 'test.html.twig',
        code: '<template><mt-progress-bar v-bind="progressProps" value="50" /></template>',
        output: null,
        errors: [
            {
                message: '[mt-progress-bar] The "value" API is deprecated. Use "model-value" instead.',
            },
        ],
    },
    {
        name: 'does not fix show-icon inversion when object v-bind can hide banner props',
        filename: 'test.html.twig',
        code: '<template><mt-banner v-bind="bannerProps" show-icon /></template>',
        output: null,
        errors: [
            {
                message: '[mt-banner] The "show-icon" API is deprecated. Use "hide-icon" instead.',
            },
        ],
    },
];

module.exports = {
    valid: [],
    invalid: objectVBindInvalidTests,
};
