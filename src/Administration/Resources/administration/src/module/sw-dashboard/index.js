import { Module } from 'src/core/shopware';
import './src/component/page/dashboard';

Module.register('sw-dashboard', {
    type: 'core',
    name: 'Core Dashboard Module',
    description: 'Enter description here...',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#6abff0',

    routes: {
        index: {
            component: 'sw-dashboard',
            path: 'index'
        }
    },

    navigation: [{
        path: 'sw.dashboard.index',
        icon: 'default-device-dashboard',
        color: '#6abff0',
        label: 'Dashboard'
    }]
});
