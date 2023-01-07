import './page/sw-settings-units-list';
import './page/sw-settings-units-detail';
import './acl';

/* eslint-disable max-len, sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-settings-units-list', () => import('./page/sw-settings-units-list'));
Shopware.Component.register('sw-settings-units-detail', () => import('./page/sw-settings-units-detail'));
/* eslint-enable max-len, sw-deprecation-rules/private-feature-declarations */

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-units', {
    type: 'core',
    name: 'settings-units',
    title: 'sw-settings-units.general.mainMenuItemGeneral',
    description: 'Units section in the settings module',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',
    entity: 'units',

    routes: {
        index: {
            component: 'sw-settings-units-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'scale_unit.viewer',
            },
        },
        detail: {
            component: 'sw-settings-units-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.units.index',
                privilege: 'scale_unit.viewer',
            },
            props: {
                default(route) {
                    return {
                        unitId: route.params.id,
                    };
                },
            },
        },
        create: {
            component: 'sw-settings-units-detail',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.units.index',
                privilege: 'scale_unit.creator',
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.units.index',
        icon: 'regular-balance-scale',
        privilege: 'scale_unit.viewer',
    },
});
