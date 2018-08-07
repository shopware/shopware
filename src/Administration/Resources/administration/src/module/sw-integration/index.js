import { Module } from 'src/core/shopware';
import './page/sw-integration-list';

Module.register('sw-integration', {
    type: 'core',
    name: 'integrations',
    description: 'The module for managing integrations.',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#14D7A5',
    icon: 'default-device-server',

    routes: {
        index: {
            component: 'sw-integration-list',
            path: 'index'
        }
    },

    navigation: [{
        path: 'sw.integration.index',
        label: 'sw-integration.general.mainMenuItemIndex',
        parent: 'sw-settings-wrapper'
    }]
});
