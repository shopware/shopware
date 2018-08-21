import { Module } from 'src/core/shopware';
import './page/sw-dashboard-index';

Module.register('sw-dashboard', {
    type: 'core',
    name: 'Dashboard',
    description: 'sw-dashboard.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#6AD6F0',
    icon: 'default-device-dashboard',

    routes: {
        index: {
            components: {
                default: 'sw-dashboard-index'
            },
            path: 'index'
        }
    },

    navigation: [{
        id: 'sw-dashboard',
        label: 'sw-dashboard.general.mainMenuItemGeneral',
        color: '#6AD6F0',
        icon: 'default-device-dashboard',
        path: 'sw.dashboard.index'
    }]
});
