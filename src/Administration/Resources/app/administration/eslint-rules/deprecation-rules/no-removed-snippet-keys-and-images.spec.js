/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-removed-snippet-keys-and-images');

const scriptTester = new RuleTester({
    languageOptions: { ecmaVersion: 2022, sourceType: 'module' },
});

const templateTester = new RuleTester({
    languageOptions: {
        parser: require('vue-eslint-parser'),
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

const replace = { messageId: 'replace' };

scriptTester.run('no-removed-snippet-keys-and-images (script)', rule, {
    valid: [
        {
            name: 'ignores snippet keys that stay and plugin images',
            code: [
                'this.$t("global.sw-condition.condition.cartTaxDisplay.label");',
                'this.assetFilter("/acmeplugin/administration/static/img/cms/preview_plant_small.jpg");',
                'this.assetFilter("/administration/administration/static/img/cms/preview_unknown.jpg");',
            ].join('\n'),
        },
    ],
    invalid: [
        {
            name: 'replaces a removed snippet key',
            code: 'this.$t(\'global.sw-condition.condition.lineItemOfTypeRule.discount_surcharge\');',
            output: 'this.$t(\'global.sw-condition-generic.cartLineItemOfType.lineItemType.options.promotion\');',
            errors: [replace],
        },
        {
            name: 'replaces image paths with every asset prefix',
            code: [
                'const a = this.assetFilter("/administration/administration/static/img/cms/preview_plant_small.jpg");',
                'const b = `bundles/administration/static/img/sw-login-background.png`;',
                'const c = "administration/static/fixtures/sw-test-image.png";',
            ].join('\n'),
            output: [
                'const a = this.assetFilter("/administration/administration/static/img/cms/preview_plant_small.webp");',
                'const b = `bundles/administration/static/img/sw-login-background.webp`;',
                'const c = "administration/static/fixtures/sw-test-image.webp";',
            ].join('\n'),
            errors: [replace, replace, replace],
        },
        {
            name: 'comments images and snippet keys without replacement',
            code: [
                'const image = "/administration/static/img/cms/preview_youtube.jpg";',
                'const tooltip = "sw-import-export.profile.addNewProfileTooltipLanguage";',
            ].join('\n'),
            output: [
                '// TODO Codemod: The image img/cms/preview_youtube is removed in 6.8 without a .webp version. Core uses static/img/cms/preview_mountain_small.webp instead.',
                'const image = "/administration/static/img/cms/preview_youtube.jpg";',
                '// TODO Codemod: The snippet sw-import-export.profile.addNewProfileTooltipLanguage is removed in 6.8 without replacement.',
                'const tooltip = "sw-import-export.profile.addNewProfileTooltipLanguage";',
            ].join('\n'),
            errors: [{ messageId: 'todo' }, { messageId: 'todo' }],
        },
    ],
});

templateTester.run('no-removed-snippet-keys-and-images (template)', rule, {
    valid: [],
    invalid: [
        {
            name: 'replaces image paths in bindings and static attributes',
            filename: 'test.html.twig',
            code: [
                '<template>',
                '    <img :src="assetFilter(\'/administration/administration/static/img/cms/preview_camera_large.jpg\')" />',
                '    <img src="/bundles/administration/static/img/theme/default_theme_preview.jpg" />',
                '</template>',
            ].join('\n'),
            output: [
                '<template>',
                '    <img :src="assetFilter(\'/administration/administration/static/img/cms/preview_camera_large.webp\')" />',
                '    <img src="/bundles/administration/static/img/theme/default_theme_preview.webp" />',
                '</template>',
            ].join('\n'),
            errors: [replace, replace],
        },
    ],
});
