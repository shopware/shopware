/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-settings-customer-group-detail',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-settings-customer-group-detail that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_settings_customer_group_detail_content_card_display_gross',
            moveTo: 'sw_settings_customer_group_detail_content_card_tax_display',
            components: ['sw-settings-customer-group-detail', 'sw-settings-customer-group-create'],
        }),
    ],
});
