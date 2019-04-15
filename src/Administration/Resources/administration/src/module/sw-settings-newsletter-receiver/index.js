import { Module } from 'src/core/shopware';

import './extension/sw-settings-index';
import './page/sw-settings-newsletter-receiver-list/index';
import './page/sw-settings-newsletter-receiver-detail/index';
import './component/sw-settings-newsletter-receiver-boolean-filter';

import deDE from './snippet/de_DE.json';
import enGB from './snippet/en_GB.json';

Module.register('sw-settings-newsletter-receiver', {
    type: 'core',
    name: 'Newsletter receiver',
    description: 'The module for managing newsletter receiver.',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-products.png',
    entity: 'newsletter_receiver',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-settings-newsletter-receiver-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        },

        detail: {
            component: 'sw-settings-newsletter-receiver-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.newsletter.receiver.index'
            }
        }
    },

    navigation: [{
        id: 'sw-settings-newsletter-receiver',
        icon: 'default-action-settings',
        color: '#9AA8B5',
        path: 'sw.settings.newsletter.receiver.index',
        label: 'sw-settings-newsletter-receiver.general.mainMenuItemGeneral',
        parent: 'sw-settings'
    }]
});
