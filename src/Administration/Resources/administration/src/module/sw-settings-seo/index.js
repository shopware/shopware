import './extension/sw-settings-index';
import './page/sw-settings-seo';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('sw-settings-seo', {
    type: 'core',
    name: 'settings-seo',
    title: 'sw-settings-seo.general.mainMenuItemGeneral',
    description: 'SEO section in the settings module',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
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
    }
});
