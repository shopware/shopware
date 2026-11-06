/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-settings-storefront-configuration',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-settings-storefront-configuration that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_settings_storefront',
            moveTo: 'the block of the same name in sw-settings-storefront-index',
            components: ['sw-settings-storefront-configuration'],
            onlyInComponents: true,
        }),
        removeTemplateBlock({
            from: 'sw_settings_storefront_settings_icon_cache',
            moveTo: 'the block of the same name in sw-settings-storefront-index',
            components: ['sw-settings-storefront-configuration'],
            onlyInComponents: true,
        }),
        removeTemplateBlock({
            from: 'sw_settings_storefront_settings_speculation_rules',
            moveTo: 'the block of the same name in sw-settings-storefront-index',
            components: ['sw-settings-storefront-configuration'],
            onlyInComponents: true,
        }),
        removeTemplateBlock({
            from: 'sw_settings_storefront_settings_theme_async',
            moveTo: 'the block of the same name in sw-settings-storefront-index',
            components: ['sw-settings-storefront-configuration'],
            onlyInComponents: true,
        }),
    ],
});
