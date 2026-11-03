/**
 * @sw-package framework
 */

import { assetMigration, removeAsset, replaceExtension, upgradeGuide } from './helpers';
import type { AssetMigration } from './helpers';

export default [
    assetMigration({
        id: 'asset.admin-png-jpg-to-webp',
        deprecatedIn: '6.7.0',
        removedIn: '6.8.0',
        description:
            'Administration PNG and JPG assets are deprecated in favor of WebP variants. Update imports to the matching .webp file.',
        references: [
            upgradeGuide('UPGRADE-6.7.md#administration'),
        ],
        files: [
            '/static/img/sw-login-background.png',
            '/static/img/plugin-manager--login.png',
            '/static/img/data-consent-background.png',
            '/static/img/flowbuilder/ui-sample.png',
            '/static/img/cms/preview_plant_small.jpg',
            '/static/img/cms/preview_glasses_large.jpg',
            '/static/img/cms/preview_page_default.png',
            '/static/img/cms/preview_page_sidebar.png',
            '/static/img/cms/preview_glasses_small.jpg',
            '/static/img/cms/preview_youtube.jpg',
            '/static/img/cms/preview_plant_large.jpg',
            '/static/img/cms/preview_custom_entity_detail_default.png',
            '/static/img/cms/preview_mountain_large.jpg',
            '/static/img/cms/default_preview_product_detail.jpg',
            '/static/img/cms/preview_custom_entity_detail_sidebar.png',
            '/static/img/cms/preview_product_detail_sidebar.png',
            '/static/img/cms/preview_product_detail_default.png',
            '/static/img/cms/preview_product_list_default.png',
            '/static/img/cms/preview_product_list_sidebar.png',
            '/static/img/cms/preview_mountain_small.jpg',
            '/static/img/cms/default_preview_product_list.jpg',
            '/static/img/cms/preview_landingpage_sidebar.png',
            '/static/img/cms/vimeo-icon.png',
            '/static/img/cms/preview_landingpage_default.png',
            '/static/img/cms/youtube-icon.png',
            '/static/img/cms/preview_camera_small.jpg',
            '/static/img/cms/preview_custom_entity_list_sidebar.png',
            '/static/img/cms/preview_camera_large.jpg',
            '/static/img/cms/preview_vimeo.jpg',
            '/static/img/cms/preview_custom_entity_list_default.png',
            '/static/img/theme/default_theme_preview.jpg',
            '/static/fixtures/sw-login-background.png',
            '/static/fixtures/sw-test-image.png',
            '/static/fixtures/sw-login-background-2.png',
            '/src/module/sw-login/page/index/assets/sw-login-background.png',
        ],
        usage: [
            replaceExtension({
                from: [
                    '.png',
                    '.jpg',
                ],
                to: '.webp',
            }),
        ],
    }),
    assetMigration({
        id: 'asset.sw-login-background-webp',
        deprecatedIn: '6.7.15.0',
        removedIn: '6.8.0',
        description:
            'The login page no longer shows a background image, so its WebP background is deprecated without replacement. Ship your own image if you still need it.',
        files: [
            '/src/module/sw-login/page/index/assets/sw-login-background.webp',
        ],
        usage: [
            removeAsset(),
        ],
    }),
] satisfies AssetMigration[];
