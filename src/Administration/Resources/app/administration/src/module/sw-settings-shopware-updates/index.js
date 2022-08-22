import './page/sw-settings-shopware-updates-index';
import './page/sw-settings-shopware-updates-wizard';
import './view/sw-settings-shopware-updates-info';
import './view/sw-settings-shopware-updates-requirements';
import './view/sw-settings-shopware-updates-plugins';
import './acl';


const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-shopware-updates', {
    type: 'core',
    name: 'settings-shopware-updates',
    title: 'sw-settings-shopware-updates.general.emptyTitle',
    description: 'sw-settings-shopware-updates.general.emptyTitle',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',

    routes: {
        index: {
            component: 'sw-settings-shopware-updates-index',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index.system',
                privilege: 'system.core_update',
            },
        },
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
        to: 'sw.settings.shopware.updates.index',
        icon: 'regular-sync',
    },
});
