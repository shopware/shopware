/**
 * @sw-package framework
 */

import { removeTemplateBlock, renameTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-cms-el-config-image-slider',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-cms-el-config-image-slider that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches')],
    usage: [
        renameTemplateBlock({
            from: 'sw_cms_element_image_gallery_config_settings_display_mode',
            to: 'sw_cms_element_image_slider_config_mt_settings_display_mode',
            components: ['sw-cms-el-config-image-slider'],
            onlyInComponents: true,
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_image_gallery_config_settings_display_mode_select',
            to: 'sw_cms_element_image_slider_config_mt_settings_display_mode_select',
            components: ['sw-cms-el-config-image-slider'],
            onlyInComponents: true,
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_image_gallery_config_settings_min_height',
            to: 'sw_cms_element_image_slider_config_mt_settings_min_height',
            components: ['sw-cms-el-config-image-slider'],
            onlyInComponents: true,
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_image_gallery_config_settings_vertical_align',
            to: 'sw_cms_element_image_slider_config_mt_settings_vertical_align',
            components: ['sw-cms-el-config-image-slider'],
            onlyInComponents: true,
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_image_slider_config_content',
            to: 'sw_cms_element_image_slider_config_mt_content',
            components: ['sw-cms-el-config-image-slider'],
            fix: 'unsafe-auto',
            message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_image_slider_config_media_modal',
            to: 'sw_cms_element_image_slider_config_mt_media_modal',
            components: ['sw-cms-el-config-image-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_image_slider_config_media_selection',
            to: 'sw_cms_element_image_slider_config_mt_media_selection',
            components: ['sw-cms-el-config-image-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_image_slider_config_media_upload_listener',
            to: 'sw_cms_element_image_slider_config_mt_media_upload_listener',
            components: ['sw-cms-el-config-image-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_image_slider_config_settings',
            to: 'sw_cms_element_image_slider_config_mt_settings',
            components: ['sw-cms-el-config-image-slider'],
            fix: 'unsafe-auto',
            message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_image_slider_config_settings_auto_slide',
            to: 'sw_cms_element_image_slider_config_mt_settings_auto_slide',
            components: ['sw-cms-el-config-image-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_image_slider_config_settings_autoplay_timeout',
            to: 'sw_cms_element_image_slider_config_mt_settings_autoplay_timeout',
            components: ['sw-cms-el-config-image-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_image_slider_config_settings_display_mode',
            to: 'sw_cms_element_image_slider_config_mt_settings_display_mode',
            components: ['sw-cms-el-config-image-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_image_slider_config_settings_display_mode_select',
            to: 'sw_cms_element_image_slider_config_mt_settings_display_mode_select',
            components: ['sw-cms-el-config-image-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_image_slider_config_settings_link_aria_label',
            to: 'sw_cms_element_image_slider_config_mt_settings_link_aria_label',
            components: ['sw-cms-el-config-image-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_image_slider_config_settings_link_target',
            to: 'sw_cms_element_image_slider_config_mt_settings_link_target',
            components: ['sw-cms-el-config-image-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_image_slider_config_settings_link_url',
            to: 'sw_cms_element_image_slider_config_mt_settings_link_url',
            components: ['sw-cms-el-config-image-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_image_slider_config_settings_links',
            to: 'sw_cms_element_image_slider_config_mt_settings_links',
            components: ['sw-cms-el-config-image-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_image_slider_config_settings_min_height',
            to: 'sw_cms_element_image_slider_config_mt_settings_min_height',
            components: ['sw-cms-el-config-image-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_image_slider_config_settings_navigation',
            to: 'sw_cms_element_image_slider_config_mt_settings_navigation',
            components: ['sw-cms-el-config-image-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_image_slider_config_settings_navigation_arrow_position',
            to: 'sw_cms_element_image_slider_config_mt_settings_navigation_arrow_position',
            components: ['sw-cms-el-config-image-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_image_slider_config_settings_navigation_dots_position',
            to: 'sw_cms_element_image_slider_config_mt_settings_navigation_dots_position',
            components: ['sw-cms-el-config-image-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_image_slider_config_settings_speed',
            to: 'sw_cms_element_image_slider_config_mt_settings_speed',
            components: ['sw-cms-el-config-image-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_image_slider_config_settings_use_fetch_priority_on_first_item',
            to: 'sw_cms_element_image_slider_config_mt_settings_use_fetch_priority_on_first_item',
            components: ['sw-cms-el-config-image-slider'],
        }),
        renameTemplateBlock({
            from: 'sw_cms_element_image_slider_config_settings_vertical_align',
            to: 'sw_cms_element_image_slider_config_mt_settings_vertical_align',
            components: ['sw-cms-el-config-image-slider'],
        }),
        removeTemplateBlock({
            from: 'sw_cms_element_image_slider_config_tab_content',
            moveTo: 'sw_cms_element_image_slider_config_mt_tabs and add your tab to its tabs computed',
            components: ['sw-cms-el-config-image-slider'],
        }),
        removeTemplateBlock({
            from: 'sw_cms_element_image_slider_config_tab_options',
            moveTo: 'sw_cms_element_image_slider_config_mt_tabs and add your tab to its tabs computed',
            components: ['sw-cms-el-config-image-slider'],
        }),
    ],
});
