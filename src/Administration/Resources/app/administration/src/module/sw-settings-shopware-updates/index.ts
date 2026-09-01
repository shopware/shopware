/**
 * @sw-package framework
 */

import './acl';

const { Component, Module } = Shopware;

/** @private */
Component.register(
    'sw-settings-shopware-updates-extensions',
    () => import('./view/sw-settings-shopware-updates-extensions'),
);
/** @private */
Component.register('sw-settings-shopware-updates-wizard', () => import('./page/sw-settings-shopware-updates-wizard'));

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
    color: '#9AA8B5',
    icon: 'regular-cog',
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
