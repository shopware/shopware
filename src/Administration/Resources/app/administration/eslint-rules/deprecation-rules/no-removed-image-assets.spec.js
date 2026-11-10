/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-removed-image-assets');

const webp = (image) => `static/${image} of the Administration is removed in 6.8. Use static/${image.replace(/\.(png|jpg)$/, '.webp')} instead.`;
const YOUTUBE = 'static/img/cms/preview_youtube.jpg of the Administration was removed in 6.7.8.0 and has no .webp version. '
    + 'Use static/img/cms/youtube-icon.webp instead.';

const lines = (...rows) => rows.join('\n');
const template = (...rows) => lines('<template>', ...rows, '</template>');

const scriptTester = new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } });
const templateTester = new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022, sourceType: 'module' } });

scriptTester.run('no-removed-image-assets (scripts)', rule, {
    valid: [
        "const image = '/administration/administration/static/img/cms/preview_camera_large.webp';",
        "const image = '/administration/static/img/favicon/favicon-32x32.png';",
        {
            name: 'ignores images of other bundles with the same name',
            code: "const image = '/acmeplugin/administration/static/img/cms/preview_camera_large.jpg';",
        },
        {
            name: 'ignores dynamic paths',
            code: 'const image = `/administration/administration/static/img/cms/preview_${name}_large.jpg`;',
        },
    ],
    invalid: [
        {
            name: 'replaces the extension in strings and fixed parts of template literals',
            code: lines(
                "Shopware.Service('cmsService').registerCmsElement({",
                "    previewImage: '/administration/static/img/cms/preview_mountain_large.jpg',",
                '    icon: `${assetsPath}administration/administration/static/img/cms/youtube-icon.png`,',
                "    background: \"url('/bundles/administration/static/img/sw-login-background.png')\",",
                '});',
            ),
            output: lines(
                "Shopware.Service('cmsService').registerCmsElement({",
                "    previewImage: '/administration/static/img/cms/preview_mountain_large.webp',",
                '    icon: `${assetsPath}administration/administration/static/img/cms/youtube-icon.webp`,',
                "    background: \"url('/bundles/administration/static/img/sw-login-background.webp')\",",
                '});',
            ),
            errors: [
                { message: webp('img/cms/preview_mountain_large.jpg') },
                { message: webp('img/cms/youtube-icon.png') },
                { message: webp('img/sw-login-background.png') },
            ],
        },
        {
            name: 'marks images without .webp version',
            code: "const preview = this.assetFilter('administration/administration/static/img/cms/preview_youtube.jpg');",
            output: lines(
                `// TODO Codemod(6.8, asset-paths): ${YOUTUBE}`,
                "const preview = this.assetFilter('administration/administration/static/img/cms/preview_youtube.jpg');",
            ),
            errors: [{ message: YOUTUBE }],
        },
        {
            name: 'a second run only reports the marked image',
            code: lines(
                `// TODO Codemod(6.8, asset-paths): ${YOUTUBE}`,
                "const preview = this.assetFilter('administration/administration/static/img/cms/preview_youtube.jpg');",
            ),
            output: null,
            errors: [{ message: YOUTUBE }],
        },
    ],
});

templateTester.run('no-removed-image-assets (templates)', rule, {
    valid: [
        {
            filename: 'acme.html.twig',
            code: template('<img :src="assetFilter(\'/administration/administration/static/img/cms/preview_camera_large.webp\')">'),
        },
    ],
    invalid: [
        {
            name: 'replaces the extension in bound and static attributes and in interpolations',
            filename: 'acme.html.twig',
            code: template(
                '{% block acme_preview %}',
                '    <img :src="assetFilter(\'/administration/administration/static/img/cms/preview_camera_large.jpg\')">',
                '    <img src="/bundles/administration/static/img/cms/preview_plant_small.jpg">',
                '    {{ assetFilter(\'/administration/administration/static/img/theme/default_theme_preview.jpg\') }}',
                '{% endblock %}',
            ),
            output: template(
                '{% block acme_preview %}',
                '    <img :src="assetFilter(\'/administration/administration/static/img/cms/preview_camera_large.webp\')">',
                '    <img src="/bundles/administration/static/img/cms/preview_plant_small.webp">',
                '    {{ assetFilter(\'/administration/administration/static/img/theme/default_theme_preview.webp\') }}',
                '{% endblock %}',
            ),
            errors: [
                { message: webp('img/cms/preview_camera_large.jpg') },
                { message: webp('img/cms/preview_plant_small.jpg') },
                { message: webp('img/theme/default_theme_preview.jpg') },
            ],
        },
        {
            name: 'marks the element of an image without .webp version in a .vue template',
            filename: 'acme.vue',
            code: template(
                '    <img',
                '        :src="assetFilter(\'/administration/administration/static/img/cms/preview_youtube.jpg\')"',
                '    >',
            ),
            output: template(
                `    <!-- TODO Codemod(6.8, asset-paths): ${YOUTUBE} -->`,
                '    <img',
                '        :src="assetFilter(\'/administration/administration/static/img/cms/preview_youtube.jpg\')"',
                '    >',
            ),
            errors: [{ message: YOUTUBE }],
        },
    ],
});
