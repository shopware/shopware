/**
 * @sw-package framework
 */

import { removeTemplateBlock, renameTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-custom-field-set-renderer',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-custom-field-set-renderer that Shopware 6.8 renames or removes.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches'),
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks'),
    ],
    usage: [
        renameTemplateBlock({
            from: 'sw_custom_field_set_renderer_card_form_renderer',
            to: 'sw_custom_field_set_renderer_card_mt_tabs_form_renderer',
            components: ['sw-custom-field-set-renderer', 'sw-bulk-edit-custom-fields'],
            onlyInComponents: true,
        }),
        removeTemplateBlock({
            from: 'sw_custom_field_set_renderer_card_tabs',
            moveTo: 'sw_custom_field_set_renderer_card_mt_tabs and add your tab to its customFieldSetTabs computed',
            components: ['sw-custom-field-set-renderer', 'sw-bulk-edit-custom-fields'],
        }),
        removeTemplateBlock({
            from: 'sw_custom_field_set_renderer_card_tabs_content',
            moveTo: 'sw_custom_field_set_renderer_card_mt_tabs_content (the markup changed)',
            components: ['sw-custom-field-set-renderer', 'sw-bulk-edit-custom-fields'],
        }),
    ],
});
