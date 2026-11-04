/**
 * @sw-package framework
 */

import './acl';
import './component/sw-settings-logging-entry-info/register';
import './component/sw-settings-logging-mail-sent-info/register';
import './page/sw-settings-logging-list/register';

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-logging', {
    type: 'core',
    name: 'settings-logging',
    title: 'sw-settings-logging.general.mainMenuItemGeneral',
    description: 'Log viewer',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-server',
    favicon: 'icon-module-settings.svg',
    entity: 'log_entry',

    routes: {
        index: {
            component: 'sw-settings-logging-list',
            path: 'list',
            meta: {
                parentPath: 'sw.settings.index.system',
                privilege: 'system.logging',
            },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'sw.settings.logging.index',
        icon: 'regular-server',
        privilege: 'system.logging',
    },
});
