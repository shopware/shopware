import { Module } from 'src/core/shopware';
import { NEXT741 } from 'src/flag/feature_next741';
import './extension/sw-settings-index';
import './page/sw-settings-seo';
import './component/sw-settings-seo-entity-detail';
import deDE from './snippet/de_DE.json';
import enGB from './snippet/en_GB.json';

Module.register('sw-settings-seo', {
    flag: NEXT741,
    type: 'core',
    name: 'sw-settings-seo.general.mainMenuItemGeneral',
    description: 'SEO section in the settings module',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    entity: 'seo',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-settings-seo',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        }
    },

    navigation: [{
        label: 'sw-settings-seo.general.mainMenuItemGeneral',
        id: 'sw-settings-seo',
        color: '#9AA8B5',
        icon: 'default-action-settings',
        path: 'sw.settings.seo.index',
        parent: 'sw-settings'
    }]
});
