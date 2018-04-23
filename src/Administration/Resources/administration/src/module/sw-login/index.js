import { Module } from 'src/core/shopware';
import './page/index';

Module.register('sw-login', {
    type: 'core',
    name: 'moduleNames.login',
    description: 'Enter description here...',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#F19D12',

    routes: {
        index: {
            component: 'sw-login',
            path: '/login',
            alias: '/signin',
            coreRoute: true
        }
    }
});
