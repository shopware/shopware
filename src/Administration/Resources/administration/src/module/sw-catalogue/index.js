import { Module } from 'src/core/shopware';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Module.register('sw-catalogue', {
    type: 'core',
    name: 'catalogue',
    title: 'sw-catalogue.general.mainMenuItemGeneral',
    description: 'Catalogue Module',
    color: '#57D9A3',
    icon: 'default-symbol-products',
    favicon: 'icon-module-products.png',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-catalogue-index',
            icon: 'default-action-settings'
        }
    },

    navigation: [{
        id: 'sw-catalogue',
        label: 'sw-catalogue.general.mainMenuItemGeneral',
        color: '#57D9A3',
        icon: 'default-symbol-products',
        position: 20
    }]
});
