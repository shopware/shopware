import './extension/sw-settings-index';
import './page/sw-integration-list';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('sw-integration', {
    type: 'core',
    name: 'integration',
    title: 'sw-integration.general.mainMenuItemIndex',
    description: 'The module for managing integrations.',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'integration',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'sw-integration-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        }
    }
});
