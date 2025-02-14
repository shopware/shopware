/**
 * @sw-package checkout
 */
import './acl';

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-settings-state-machine-list', () => import('./page/sw-settings-state-machine-list'));
Shopware.Component.register('sw-settings-state-machine-detail', () => import('./page/sw-settings-state-machine-detail'));
Shopware.Component.register(
    'sw-settings-state-machine-state-list',
    () => import('./component/sw-settings-state-machine-state-list'),
);
Shopware.Component.register(
    'sw-settings-state-machine-state-detail',
    () => import('./component/sw-settings-state-machine-state-detail'),
);
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-state-machine', {
    type: 'core',
    name: 'settings-state-machine',
    title: 'sw-settings-state-machine.general.mainMenuItemGeneral',
    description: 'State machine section in the settings module',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',
    entity: 'state_machine',

    routes: {
        index: {
            component: 'sw-settings-state-machine-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'state_machine.viewer',
            },
        },
        detail: {
            component: 'sw-settings-state-machine-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.state.machine.index',
                privilege: 'state_machine.viewer',
            },
            props: {
                default(route) {
                    return {
                        stateMachineId: route.params.id,
                    };
                },
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.state.machine.index',
        icon: 'regular-history',
        privilege: 'state_machine.viewer',
    },
});
