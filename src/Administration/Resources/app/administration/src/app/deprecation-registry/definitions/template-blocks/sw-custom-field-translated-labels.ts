/**
 * @sw-package framework
 */

import { removeTemplateBlock, renameTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-custom-field-translated-labels',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-custom-field-translated-labels that Shopware 6.8 renames or removes.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches'),
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks'),
    ],
    usage: [
        renameTemplateBlock({
            from: 'sw_custom_field_translated_labels_translated_content_field',
            to: 'sw_custom_field_translated_labels_translated_mt_content_field',
            components: ['sw-custom-field-translated-labels'],
        }),
        removeTemplateBlock({
            from: 'sw_custom_field_translated_labels_translated_content',
            moveTo: 'sw_custom_field_translated_labels_translated_mt_content (the markup changed)',
            components: ['sw-custom-field-translated-labels'],
        }),
        removeTemplateBlock({
            from: 'sw_custom_field_translated_labels_translated_tabs',
            moveTo: 'sw_custom_field_translated_labels_translated_mt_tabs and add your tab to its translatedLabelTabs computed',
            components: ['sw-custom-field-translated-labels'],
        }),
    ],
});
