import './extension/sw-admin-menu';
import './page/sw-profile-index';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('sw-profile', {
    type: 'core',
    name: 'profile',
    title: 'sw-profile.general.headlineProfile',
    description: 'sw-profile.general.description',
    color: '#9AA8B5',
    icon: 'default-avatar-single',
    entity: 'user',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-profile-index',
            path: 'index'
        }
    }
});
