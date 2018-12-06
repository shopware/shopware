import { Module } from 'src/core/shopware';
import { NEXT717 } from 'src/flag/feature_next717';

import './extension/sw-settings-index';
import './page/sw-settings-snippet-set-list';

Module.register('sw-settings-snippet-set', {
    flag: NEXT717,
    type: 'core',
    name: 'Language-Set',
    description: 'The shopware snippet set module',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#2471C8',
    icon: 'default-location-flag',

    routes: {
        index: {
            component: 'sw-settings-snippet-set-list',
            path: 'index'
        }
    },

    navigation: [{
        id: 'sw-settings-snippet-set',
        icon: 'default-action-settings',
        label: 'Snippet Set',
        color: '#2471C8',
        path: 'sw.settings.snippet.set.index',
        parent: 'sw-settings'
    }]
});
