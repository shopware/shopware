import { Module } from 'src/core/shopware';
import './src/component/page/sw-login';

Module.register('sw-login', {
    type: 'core',
    name: 'Core Login Module',
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
