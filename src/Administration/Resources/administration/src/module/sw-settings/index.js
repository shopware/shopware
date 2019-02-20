import { Module } from 'src/core/shopware';
import './component/sw-settings-item';
import './page/sw-settings-index';
import './mixin/sw-settings-list.mixin';

Module.register('sw-settings', {
    type: 'core',
    name: 'Settings',
    description: 'Settings Module',
    color: '#9AA8B5',
    icon: 'default-action-settings',

    routes: {
        index: {
            component: 'sw-settings-index',
            path: 'index',
            icon: 'default-action-settings'
        }
    },

    navigation: [{
        id: 'sw-settings',
        label: 'sw-settings.general.mainMenuItemGeneral',
        color: '#9AA8B5',
        icon: 'default-action-settings',
        path: 'sw.settings.index',
        position: 70
    }]
});
