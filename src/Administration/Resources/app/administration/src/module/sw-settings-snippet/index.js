import './extension/sw-settings-index';
import './page/sw-settings-snippet-set-list';
import './page/sw-settings-snippet-list';
import './page/sw-settings-snippet-detail';
import './page/sw-settings-snippet-create';
import './component/sidebar/sw-settings-snippet-sidebar';
import './component/sidebar/sw-settings-snippet-filter-switch';

const { Module } = Shopware;

Module.register('sw-settings-snippet', {
    type: 'core',
    name: 'settings-snippet',
    title: 'sw-settings-snippet.general.mainMenuItemGeneral',
    description: 'sw-settings-snippet.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'snippet',

    routes: {
        index: {
            component: 'sw-settings-snippet-set-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        },
        list: {
            component: 'sw-settings-snippet-list',
            path: 'list',
            meta: {
                parentPath: 'sw.settings.snippet.index'
            }
        },
        detail: {
            component: 'sw-settings-snippet-detail',
            path: 'detail/:key',
            meta: {
                parentPath: 'sw.settings.snippet.list'
            }
        },
        create: {
            component: 'sw-settings-snippet-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.snippet.list'
            }
        }
    }
});
