import { Module } from 'src/core/shopware';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Module.register('sw-content', {
    type: 'core',
    name: 'content',
    title: 'sw-catalogue.general.mainMenuItemGeneral',
    description: 'Content Modules',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#FFD700',
    icon: 'default-object-image',
    favicon: 'icon-module-media.png',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-content-index'
        }
    },

    navigation: [{
        id: 'sw-content',
        label: 'sw-content.general.mainMenuItemGeneral',
        color: '#FFD700',
        icon: 'default-object-image',
        position: 50
    }]
});
