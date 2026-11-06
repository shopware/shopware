/**
 * @sw-package framework
 */

import { removeTemplateBlock, renameTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-settings-logging-mail-sent-info',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-settings-logging-mail-sent-info that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches')],
    usage: [
        renameTemplateBlock({
            from: 'sw_settings_logging_mail_sent_content_mailbody',
            to: 'sw_settings_logging_mail_sent_mt_content_mailbody',
            components: ['sw-settings-logging-mail-sent-info'],
        }),
        renameTemplateBlock({
            from: 'sw_settings_logging_mail_sent_content_mailbody_html',
            to: 'sw_settings_logging_mail_sent_mt_content_mailbody_html',
            components: ['sw-settings-logging-mail-sent-info'],
        }),
        renameTemplateBlock({
            from: 'sw_settings_logging_mail_sent_content_mailbody_plain',
            to: 'sw_settings_logging_mail_sent_mt_content_mailbody_plain',
            components: ['sw-settings-logging-mail-sent-info'],
        }),
        renameTemplateBlock({
            from: 'sw_settings_logging_mail_sent_content_mailbody_title',
            to: 'sw_settings_logging_mail_sent_mt_content_mailbody_title',
            components: ['sw-settings-logging-mail-sent-info'],
        }),
        renameTemplateBlock({
            from: 'sw_settings_logging_mail_sent_content_recipients',
            to: 'sw_settings_logging_mail_sent_mt_content_recipients',
            components: ['sw-settings-logging-mail-sent-info'],
        }),
        renameTemplateBlock({
            from: 'sw_settings_logging_mail_sent_content_recipients_title',
            to: 'sw_settings_logging_mail_sent_mt_content_recipients_title',
            components: ['sw-settings-logging-mail-sent-info'],
        }),
        removeTemplateBlock({
            from: 'sw_settings_logging_entry_info_tab_items',
            moveTo: 'sw_settings_logging_entry_info_mt_tabs and add your tab to its entryInfoTabs computed',
            components: ['sw-settings-logging-mail-sent-info'],
            onlyInComponents: true,
        }),
    ],
});
