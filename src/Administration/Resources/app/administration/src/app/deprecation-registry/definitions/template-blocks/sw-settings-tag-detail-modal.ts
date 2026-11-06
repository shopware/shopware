/**
 * @sw-package framework
 */

import { removeTemplateBlock, renameTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-settings-tag-detail-modal',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-settings-tag-detail-modal that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches')],
    usage: [
        renameTemplateBlock({
            from: 'sw_settings_tag_detail_modal_tabs_assignments_tab',
            to: 'sw_settings_tag_detail_modal_mt_tabs_assignments_tab',
            components: ['sw-settings-tag-detail-modal'],
        }),
        renameTemplateBlock({
            from: 'sw_settings_tag_detail_modal_tabs_general_tab',
            to: 'sw_settings_tag_detail_modal_mt_tabs_general_tab',
            components: ['sw-settings-tag-detail-modal'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_tag_detail_modal_tabs_assignments',
            moveTo: 'sw_settings_tag_detail_modal_mt_tabs and add your tab to its tagDetailModalTabs computed',
            components: ['sw-settings-tag-detail-modal'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_tag_detail_modal_tabs_general',
            moveTo: 'sw_settings_tag_detail_modal_mt_tabs and add your tab to its tagDetailModalTabs computed',
            components: ['sw-settings-tag-detail-modal'],
        }),
    ],
});
