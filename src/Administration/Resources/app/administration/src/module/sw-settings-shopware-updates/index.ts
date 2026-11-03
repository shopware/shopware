/**
 * @sw-package framework
 */

import './acl';
import './page/sw-settings-shopware-updates-wizard/register';
import './view/sw-settings-shopware-updates-extensions/register';

const { Module } = Shopware;

/**
 * @private
 */
Module.register('sw-settings-shopware-updates', {
    type: 'core',
    name: 'settings-shopware-updates',
    display: !Shopware.Context.app.hideUpdateModule,
    title: 'sw-settings-shopware-updates.general.menuTitle',
    description: 'sw-settings-shopware-updates.general.menuTitle',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-sync',
    favicon: 'icon-module-settings.svg',

    routes: {
        wizard: {
            component: 'sw-settings-shopware-updates-wizard',
            path: 'wizard',
            meta: {
                parentPath: 'sw.settings.index.system',
                privilege: 'system.core_update',
            },
        },
    },

    settingsItem: {
        privilege: 'system.core_update',
        group: 'system',
        to: 'sw.settings.shopware.updates.wizard',
        icon: 'regular-sync',
    },
});
