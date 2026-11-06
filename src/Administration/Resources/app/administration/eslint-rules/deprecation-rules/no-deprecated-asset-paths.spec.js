/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-deprecated-asset-paths');

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

scriptTester.run('no-deprecated-asset-paths (script)', rule, {
    valid: [
        {
            name: 'ignores plugin assets and converted files',
            code: [
                'this.assetFilter("/acmeplugin/administration/static/img/cms/preview_plant_small.jpg");',
                'this.assetFilter("/administration/administration/static/img/cms/preview_plant_small.webp");',
            ].join('\n'),
        },
    ],
    invalid: [
        {
            name: 'points paths with every asset prefix to the converted file',
            code: [
                'const a = this.assetFilter("/administration/administration/static/img/cms/preview_plant_small.jpg");',
                'const b = `bundles/administration/static/img/sw-login-background.png`;',
            ].join('\n'),
            output: [
                'const a = this.assetFilter("/administration/administration/static/img/cms/preview_plant_small.webp");',
                'const b = `bundles/administration/static/img/sw-login-background.webp`;',
            ].join('\n'),
            errors: 2,
        },
        {
            name: 'reports a removed asset without a converted file',
            code: 'const image = "/administration/static/img/cms/preview_youtube.jpg";',
            errors: [{ message: /preview_youtube\.jpg" is deprecated\. Remove it, there is no replacement\./ }],
        },
    ],
});

templateTester.run('no-deprecated-asset-paths (template)', rule, {
    valid: [],
    invalid: [
        {
            name: 'points bindings and static attributes to the converted file',
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
            errors: 2,
        },
    ],
});
