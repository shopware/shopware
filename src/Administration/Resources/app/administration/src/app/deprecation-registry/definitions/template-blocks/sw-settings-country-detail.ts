/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-settings-country-detail',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-settings-country-detail that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches')],
    usage: [
        removeTemplateBlock({
            from: 'sw_setting_country_tabs_address_handling',
            moveTo: 'sw_settings_country_mt_tabs_header and add your tab to its countryTabs computed',
            components: ['sw-settings-country-detail', 'sw-settings-country-create'],
        }),
        removeTemplateBlock({
            from: 'sw_setting_country_tabs_extension',
            moveTo: 'sw_settings_country_mt_tabs_header and add your tab to its countryTabs computed',
            components: ['sw-settings-country-detail', 'sw-settings-country-create'],
        }),
        removeTemplateBlock({
            from: 'sw_setting_country_tabs_setting',
            moveTo: 'sw_settings_country_mt_tabs_header and add your tab to its countryTabs computed',
            components: ['sw-settings-country-detail', 'sw-settings-country-create'],
        }),
        removeTemplateBlock({
            from: 'sw_setting_country_tabs_state',
            moveTo: 'sw_settings_country_mt_tabs_header and add your tab to its countryTabs computed',
            components: ['sw-settings-country-detail', 'sw-settings-country-create'],
        }),
    ],
});
