import './component/sw-dashboard-external-link';
import './component/sw-dashboard-statistics';
import './page/sw-dashboard-index';

const { Module } = Shopware;

Module.register('sw-dashboard', {
    type: 'core',
    name: 'dashboard',
    title: 'sw-dashboard.general.mainMenuItemGeneral',
    description: 'sw-dashboard.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#6AD6F0',
    icon: 'default-device-dashboard',
    favicon: 'icon-module-dashboard.png',

    routes: {
        index: {
            components: {
                default: 'sw-dashboard-index',
            },
            path: 'index',
        },
    },

    navigation: [{
        id: 'sw-dashboard',
        label: 'sw-dashboard.general.mainMenuItemGeneral',
        color: '#6AD6F0',
        icon: 'default-device-dashboard',
        path: 'sw.dashboard.index',
        position: 10,
    }],
});
