/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-bulk-edit-custom-fields',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-bulk-edit-custom-fields that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches')],
    usage: [
        removeTemplateBlock({
            from: 'sw_custom_field_set_renderer_card_form_renderer_change_field',
            moveTo: 'sw_custom_field_set_renderer_card_mt_tabs_form_renderer',
            components: ['sw-bulk-edit-custom-fields'],
        }),
        removeTemplateBlock({
            from: 'sw_custom_field_set_renderer_card_form_renderer_custom_field',
            moveTo: 'sw_custom_field_set_renderer_card_mt_tabs_form_renderer',
            components: ['sw-bulk-edit-custom-fields'],
        }),
    ],
});
