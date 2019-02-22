import { Module } from 'src/core/shopware';
import './extension/sw-admin-menu';
import './page/sw-profile-index';

Module.register('sw-profile', {
    type: 'core',
    name: 'Profile',
    description: 'The user profile settings.',
    color: '#9AA8B5',
    icon: 'default-avatar-single',
    entity: 'user',

    routes: {
        index: {
            component: 'sw-profile-index',
            path: 'index'
        }
    }
});
