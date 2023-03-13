import './page/index';

import type { Route } from 'vue-router';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

/**
 * @package admin
 *
 * @private
 */
Module.register('sw-inactivity-login', {
    type: 'core',
    name: 'inactivity-login',
    title: 'sw-inactivity-login.general.mainMenuItemIndex',
    description: 'sw-inactivity-login.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#F19D12',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
    },

    routes: {
        index: {
            component: 'sw-inactivity-login',
            path: '/inactivity/login/:id',
            coreRoute: true,
            props: {
                default(route: Route) {
                    return {
                        hash: route.params.id,
                    };
                },
            },
        },
    },
});
