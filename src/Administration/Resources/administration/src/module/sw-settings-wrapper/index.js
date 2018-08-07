import { Module } from 'src/core/shopware';

import './component/sw-settings-wrapper-item';
import './page/sw-settings-wrapper-index';

Module.register('sw-settings-wrapper', {
    type: 'core',
    name: 'Settings',
    description: 'Settings Module',
    color: '#9AA8B5',
    icon: 'default-action-settings',

    routes: {
        index: {
            component: 'sw-settings-wrapper-index',
            path: 'index'
        }
    },

    navigation: [{
        id: 'sw-settings-wrapper',
        label: 'sw-settings-wrapper.general.mainMenuItemGeneral',
        color: '#9AA8B5',
        icon: 'default-action-settings',
        path: 'sw.settings.wrapper.index'
    }]
});
