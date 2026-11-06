/**
 * @sw-package framework
 */

import { removeTemplateBlock, renameTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-settings-logging-entry-info',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-settings-logging-entry-info that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches')],
    usage: [
        renameTemplateBlock({
            from: 'sw_settings_logging_entry_info_content',
            to: 'sw_settings_logging_entry_info_mt_content',
            components: ['sw-settings-logging-entry-info', 'sw-settings-logging-mail-sent-info'],
            onlyInComponents: true,
        }),
        renameTemplateBlock({
            from: 'sw_settings_logging_entry_info_raw_content',
            to: 'sw_settings_logging_entry_info_mt_raw_content',
            components: ['sw-settings-logging-entry-info', 'sw-settings-logging-mail-sent-info'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_logging_entry_info_tab_items',
            moveTo: 'sw_settings_logging_entry_info_mt_tabs and add your tab to its entryInfoTabs computed',
            components: ['sw-settings-logging-entry-info', 'sw-settings-logging-mail-sent-info'],
            onlyInComponents: true,
        }),
    ],
});
