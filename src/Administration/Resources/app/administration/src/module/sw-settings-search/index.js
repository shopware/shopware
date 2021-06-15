import './page/sw-settings-search';
import './view/sw-settings-search-view-general';
import './view/sw-settings-search-view-live-search';
import './component/sw-settings-search-search-behaviour';
import './component/sw-settings-search-searchable-content';
import './component/sw-settings-search-example-modal';
import './component/sw-settings-search-searchable-content-general';
import './component/sw-settings-search-searchable-content-customfields';
import './component/sw-settings-search-excluded-search-terms';
import './component/sw-settings-search-search-index';
import './component/sw-settings-search-live-search';
import './component/sw-settings-search-live-search-keyword';
import './init/services.init';
import './acl';

const { Module } = Shopware;

Module.register('sw-settings-search', {
    type: 'core',
    name: 'settings-product-search-config',
    title: 'sw-settings-search.general.mainMenuItemGeneral',
    description: 'sw-settings-snippet.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'product_search_config',

    routes: {
        index: {
            component: 'sw-settings-search',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'product_search_config.viewer',
            },

            redirect: {
                name: 'sw.settings.search.index.general',
            },

            children: {
                general: {
                    component: 'sw-settings-search-view-general',
                    path: 'general',
                    meta: {
                        parentPath: 'sw.settings.index',
                        privilege: 'product_search_config.viewer',
                    },
                },

                liveSearch: {
                    component: 'sw-settings-search-view-live-search',
                    path: 'live-search',
                    meta: {
                        parentPath: 'sw.settings.index',
                        privilege: 'product_search_config.viewer',
                    },
                },
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.search.index',
        icon: 'default-action-search',
        privilege: 'product_search_config.viewer',
    },
});
