/**
 * @sw-package framework
 *
 * The `.png` and `.jpg` files of the Administration's `static` folder that 6.8 removes, from "Removed .png and .jpg
 * images" in UPGRADE-6.8.md. Paths are relative to `administration/static/`.
 */

module.exports = {
    // The `.webp` file with the same name exists since 6.7.6.0
    webp: [
        'img/sw-login-background.png',
        'img/plugin-manager--login.png',
        'img/data-consent-background.png',
        'img/flowbuilder/ui-sample.png',
        'img/theme/default_theme_preview.jpg',
        'img/cms/default_preview_product_detail.jpg',
        'img/cms/default_preview_product_list.jpg',
        'img/cms/preview_camera_large.jpg',
        'img/cms/preview_camera_small.jpg',
        'img/cms/preview_custom_entity_detail_default.png',
        'img/cms/preview_custom_entity_detail_sidebar.png',
        'img/cms/preview_custom_entity_list_default.png',
        'img/cms/preview_custom_entity_list_sidebar.png',
        'img/cms/preview_glasses_large.jpg',
        'img/cms/preview_glasses_small.jpg',
        'img/cms/preview_landingpage_default.png',
        'img/cms/preview_landingpage_sidebar.png',
        'img/cms/preview_mountain_large.jpg',
        'img/cms/preview_mountain_small.jpg',
        'img/cms/preview_page_default.png',
        'img/cms/preview_page_sidebar.png',
        'img/cms/preview_plant_large.jpg',
        'img/cms/preview_plant_small.jpg',
        'img/cms/preview_product_detail_default.png',
        'img/cms/preview_product_detail_sidebar.png',
        'img/cms/preview_product_list_default.png',
        'img/cms/preview_product_list_sidebar.png',
        // Removed in 6.7.8.0 already
        'img/cms/vimeo-icon.png',
        'img/cms/youtube-icon.png',
        'fixtures/sw-login-background.png',
        'fixtures/sw-login-background-2.png',
        'fixtures/sw-test-image.png',
    ],

    // Removed in 6.7.8.0 without a `.webp` file of the same name
    replaced: {
        'img/cms/preview_vimeo.jpg': 'img/cms/vimeo-icon.webp',
        'img/cms/preview_youtube.jpg': 'img/cms/youtube-icon.webp',
    },
};
