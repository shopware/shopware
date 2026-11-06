/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-settings-storefront-index',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-settings-storefront-index that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_settings_storefront_smtp_settings',
            moveTo: 'sw_settings_storefront_settings_icon_cache + sw_settings_storefront_settings_speculation_rules',
            components: ['sw-settings-storefront-index'],
        }),
    ],
});
