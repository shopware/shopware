import './component/sw-settings-item';
import './component/sw-system-config';
import './page/sw-settings-index';
import './mixin/sw-settings-list.mixin';
import './acl';

const { Module } = Shopware;

Module.register('sw-settings', {
    type: 'core',
    name: 'settings',
    title: 'sw-settings.general.mainMenuItemGeneral',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',

    routes: {
        index: {
            component: 'sw-settings-index',
            path: 'index',
            icon: 'default-action-settings',
        },
    },

    navigation: [{
        id: 'sw-settings',
        label: 'sw-settings.general.mainMenuItemGeneral',
        color: '#9AA8B5',
        icon: 'default-action-settings',
        path: 'sw.settings.index',
        position: 80,
    }],
});
