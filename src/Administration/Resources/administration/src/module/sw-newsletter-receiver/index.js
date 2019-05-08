import { Module } from 'src/core/shopware';

import './page/sw-newsletter-receiver-list/index';
import './page/sw-newsletter-receiver-detail/index';
import './component/sw-newsletter-receiver-boolean-filter';

import deDE from './snippet/de_DE.json';
import enGB from './snippet/en_GB.json';

Module.register('sw-newsletter-receiver', {
    type: 'core',
    name: 'newsletter-receiver',
    title: 'sw-newsletter-receiver.general.mainMenuItemGeneral',
    description: 'sw-newsletter-receiver.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#FFD700',
    icon: 'default-object-marketing',
    favicon: 'icon-module-marketing.png',
    entity: 'newsletter_receiver',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-newsletter-receiver-list',
            path: 'index'
        },

        detail: {
            component: 'sw-newsletter-receiver-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.newsletter.receiver.index'
            }
        }
    },

    navigation: [{
        id: 'sw-newsletter-receiver',
        icon: 'default-object-marketing',
        color: '#9AA8B5',
        path: 'sw.newsletter.receiver.index',
        label: 'sw-newsletter-receiver.general.mainMenuItemGeneral',
        parent: 'sw-marketing'
    }]
});
