import './extension/sw-settings-index';
import './page/sw-settings-sitemap';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('sw-settings-sitemap', {
    type: 'core',
    name: 'settings-sitemap',
    title: 'sw-settings-sitemap.general.mainMenuItemGeneral',
    description: 'sw-settings-sitemap.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-settings-sitemap',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        }
    }
});
