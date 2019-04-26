import { Module } from 'src/core/shopware';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Module.register('sw-marketing', {
    type: 'core',
    name: 'marketing',
    title: 'sw-marketing.general.mainMenuItemGeneral',
    description: 'Marketing Module',
    color: '#DE94DE',
    icon: 'default-package-gift',
    favicon: 'default-package-gift.png',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-marketing-index',
            icon: 'default-action-settings'
        }
    },

    navigation: [{
        id: 'sw-marketing',
        label: 'sw-marketing.general.mainMenuItemGeneral',
        color: '#DE94DE',
        icon: 'default-package-gift',
        position: 70
    }]
});
