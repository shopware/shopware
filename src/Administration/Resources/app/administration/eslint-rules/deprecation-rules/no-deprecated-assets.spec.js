const path = require('path');
const { RuleTester } = require('eslint');
const rule = require('./no-deprecated-assets');

const ADMINISTRATION = path.resolve(__dirname, '../..');
const EXTENSION_SCRIPT = '/extensions/SwagExample/src/Resources/app/administration/src/main.js';
const EXTENSION_TEMPLATE = '/extensions/SwagExample/src/Resources/app/administration/src/sw-example.html.twig';
const LOGIN_PAGE = path.join(ADMINISTRATION, 'src/module/sw-login/page/index/index.js');
const CONSENT_BANNER = path.join(
    ADMINISTRATION,
    'src/module/sw-settings-usage-data/component/sw-usage-data-consent-banner/index.js',
);

const replaced = (from, to) => ({
    message: new RegExp(
        `^The Administration asset "${from.replace(/[.?]/g, '\\$&')}" is removed\\. Use "${to.replace(/[.?]/g, '\\$&')}" instead\\.\\n`,
    ),
});

const tester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

tester.run('no-deprecated-assets', rule, {
    valid: [
        {
            name: 'allows the WebP version',
            filename: EXTENSION_SCRIPT,
            code: "const image = assetFilter('administration/static/img/sw-login-background.webp');",
        },
        {
            name: 'allows an image of the Administration bundle that stays',
            filename: EXTENSION_SCRIPT,
            code: "const image = assetFilter('/administration/administration/static/img/cms/preview_unknown.png');",
        },
        {
            name: 'allows an image in the bundle of an extension',
            filename: EXTENSION_SCRIPT,
            code: "const image = assetFilter('/bundles/swagexample/administration/static/img/cms/preview_plant_small.jpg');",
        },
        {
            name: 'allows a root path in an extension, which resolves against the extension',
            filename: EXTENSION_SCRIPT,
            code: "import background from '/static/img/sw-login-background.png';",
        },
        {
            name: 'allows a relative import that stays inside the extension',
            filename: EXTENSION_SCRIPT,
            code: "import background from '../static/img/sw-login-background.png';",
        },
    ],
    invalid: [
        {
            name: 'replaces the extension of an asset filter path',
            filename: EXTENSION_SCRIPT,
            code: "const image = assetFilter('administration/static/img/sw-login-background.png');",
            output: "const image = assetFilter('administration/static/img/sw-login-background.webp');",
            errors: [
                replaced(
                    'administration/static/img/sw-login-background.png',
                    'administration/static/img/sw-login-background.webp',
                ),
            ],
        },
        {
            name: 'replaces the extension of a bundle URL and keeps the double quotes',
            filename: EXTENSION_SCRIPT,
            code: 'const image = "/bundles/administration/static/img/cms/preview_plant_small.jpg";',
            output: 'const image = "/bundles/administration/static/img/cms/preview_plant_small.webp";',
            errors: [
                replaced(
                    '/bundles/administration/static/img/cms/preview_plant_small.jpg',
                    '/bundles/administration/static/img/cms/preview_plant_small.webp',
                ),
            ],
        },
        {
            name: 'replaces the extension of a Vite build path in a template literal and keeps the query',
            filename: EXTENSION_SCRIPT,
            code: 'const image = `/administration/administration/static/img/theme/default_theme_preview.jpg?v=2`;',
            output: 'const image = `/administration/administration/static/img/theme/default_theme_preview.webp?v=2`;',
            errors: [
                replaced(
                    '/administration/administration/static/img/theme/default_theme_preview.jpg',
                    '/administration/administration/static/img/theme/default_theme_preview.webp',
                ),
            ],
        },
        {
            name: 'replaces the extension of a root path in the Administration',
            filename: path.join(ADMINISTRATION, 'src/module/sw-flow/index.js'),
            code: "import sample from '/static/img/flowbuilder/ui-sample.png';",
            output: "import sample from '/static/img/flowbuilder/ui-sample.webp';",
            errors: [replaced('/static/img/flowbuilder/ui-sample.png', '/static/img/flowbuilder/ui-sample.webp')],
        },
        {
            name: 'points a relative import in the Administration to the replacement',
            filename: LOGIN_PAGE,
            code: "import background from './assets/sw-login-background.png';",
            output: "import background from '../../../../../static/img/sw-login-background.webp';",
            errors: [replaced('./assets/sw-login-background.png', '../../../../../static/img/sw-login-background.webp')],
        },
        {
            name: 'points an alias import in the Administration to the replacement',
            filename: path.join(ADMINISTRATION, 'src/app/component/sw-x/index.js'),
            code: "import background from 'src/module/sw-login/page/index/assets/sw-login-background.png';",
            output: "import background from '/static/img/sw-login-background.webp';",
            errors: [
                replaced('src/module/sw-login/page/index/assets/sw-login-background.png', '/static/img/sw-login-background.webp'),
            ],
        },
        {
            name: 'points a module asset to the same image in the static folder',
            filename: CONSENT_BANNER,
            code: "import background from './assets/data-consent-background.png';",
            output: "import background from '../../../../../static/img/data-consent-background.webp';",
            errors: [
                replaced('./assets/data-consent-background.png', '../../../../../static/img/data-consent-background.webp'),
            ],
        },
        {
            name: 'leaves a replacement with another picture to the review level',
            filename: EXTENSION_SCRIPT,
            code: "const preview = assetFilter('/administration/administration/static/img/cms/preview_youtube.jpg');",
            output: null,
            errors: [
                {
                    message:
                        /^The Administration asset "[^"]+preview_youtube\.jpg" is removed\. Use "\/administration\/administration\/static\/img\/cms\/preview_mountain_small\.webp" instead\.\nThe replacement shows another picture/,
                },
            ],
        },
        {
            name: 'replaces an asset with another picture and a review comment at the review level',
            filename: EXTENSION_SCRIPT,
            settings: { 'sw-deprecation-rules': { level: 'review' } },
            code: "const preview = assetFilter('/administration/administration/static/img/cms/preview_vimeo.jpg');",
            output:
                '/* TODO Codemod: The Administration asset "/administration/administration/static/img/cms/preview_vimeo.jpg" is removed. ' +
                'Use "/administration/administration/static/img/cms/preview_mountain_small.webp" instead. ' +
                'The replacement shows another picture: check that it fits where the preview is shown. */\n' +
                "const preview = assetFilter('/administration/administration/static/img/cms/preview_mountain_small.webp');",
            errors: [{ message: /preview_vimeo\.jpg" is removed/ }],
        },
        {
            name: 'reports an asset without replacement',
            filename: LOGIN_PAGE,
            code: "import background from './assets/sw-login-background.webp';",
            output: null,
            errors: [
                {
                    message:
                        /^The Administration asset "\.\/assets\/sw-login-background\.webp" is removed without replacement\.\nThe login page no longer shows a background image/,
                },
            ],
        },
        {
            name: 'only reports with disableFix',
            filename: EXTENSION_SCRIPT,
            options: ['disableFix'],
            code: "const image = assetFilter('administration/static/img/sw-login-background.png');",
            output: null,
            errors: [{ message: /sw-login-background\.png" is removed/ }],
        },
    ],
});

const templateTester = new RuleTester({
    languageOptions: {
        parser: require('vue-eslint-parser'),
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

templateTester.run('no-deprecated-assets in templates', rule, {
    valid: [
        {
            name: 'allows a static attribute with the WebP version',
            filename: EXTENSION_TEMPLATE,
            code: '<template><img src="/bundles/administration/static/img/cms/preview_camera_small.webp"></template>',
        },
    ],
    invalid: [
        {
            name: 'replaces the extension in a static attribute',
            filename: EXTENSION_TEMPLATE,
            code: '<template><img src="/bundles/administration/static/img/cms/preview_camera_small.jpg"></template>',
            output: '<template><img src="/bundles/administration/static/img/cms/preview_camera_small.webp"></template>',
            errors: [{ message: /preview_camera_small\.jpg" is removed\. Use "[^"]+preview_camera_small\.webp" instead/ }],
        },
        {
            name: 'replaces the extension in an unquoted static attribute',
            filename: EXTENSION_TEMPLATE,
            code: '<template><img src=administration/static/img/cms/vimeo-icon.png></template>',
            output: '<template><img src=administration/static/img/cms/vimeo-icon.webp></template>',
            errors: [{ message: /vimeo-icon\.png" is removed/ }],
        },
        {
            name: 'replaces the extension in an asset filter binding',
            filename: EXTENSION_TEMPLATE,
            code: `<template><img :src="assetFilter('/administration/administration/static/img/cms/preview_camera_large.jpg')"></template>`,
            output: `<template><img :src="assetFilter('/administration/administration/static/img/cms/preview_camera_large.webp')"></template>`,
            errors: [{ message: /preview_camera_large\.jpg" is removed/ }],
        },
    ],
});
