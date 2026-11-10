/**
 * @sw-package framework
 */

import { templateBlockMigration, upgradeGuide } from '../helpers';
import { removeBlocks } from './block-steps';

export default templateBlockMigration({
    id: 'template-block.storefront-settings',
    deprecatedIn: '6.8.0',
    removedIn: '6.8.0',
    description:
        'sw-settings-storefront-configuration was removed, the Storefront settings page renders its fields itself. ' +
        'The page also lost its SMTP settings block.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#sw-settings-storefront-index'),
        upgradeGuide('UPGRADE-6.8.md#sw-settings-storefront-configuration'),
        upgradeGuide('UPGRADE-6.8.md#removal-of-component-sw-settings-storefront-configuration'),
    ],
    usage: [
        ...removeBlocks(
            'sw-settings-storefront-configuration',
            [
                'sw_settings_storefront',
                'sw_settings_storefront_settings_icon_cache',
                'sw_settings_storefront_settings_speculation_rules',
                'sw_settings_storefront_settings_theme_async',
            ],
            { message: 'Override the block of the same name in "sw-settings-storefront-index" instead.' },
        ),
        ...removeBlocks('sw-settings-storefront-index', ['sw_settings_storefront_smtp_settings'], {
            to: 'sw_settings_storefront_settings_icon_cache',
            message:
                'Its content moved to "sw_settings_storefront_settings_icon_cache" and ' +
                '"sw_settings_storefront_settings_speculation_rules".',
        }),
    ],
});
