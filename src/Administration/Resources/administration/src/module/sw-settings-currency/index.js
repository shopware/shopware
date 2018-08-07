import { Module } from 'src/core/shopware';

import './extension/sw-settings-wrapper-index';
import './page/sw-settings-currency-list';

Module.register('sw-settings-currency', {
    type: 'core',
    name: 'Currency settings',
    description: 'Currency section in the settings module',
    color: '#9AA8B5',
    icon: 'default-action-settings',

    routes: {
        index: {
            component: 'sw-settings-currency-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.wrapper.index'
            }
        }
    },

    navigation: [{
        label: 'sw-settings-currency.general.mainMenuItemGeneral',
        color: '#9AA8B5',
        icon: 'default-action-settings',
        path: 'sw.settings.currency.index',
        parent: 'sw-settings-wrapper'
    }]
});
