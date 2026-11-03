/**
 * @sw-package inventory
 */
import './init/services.init';
import './acl';
import './component/sw-settings-search-example-modal/register';
import './component/sw-settings-search-excluded-search-terms/register';
import './component/sw-settings-search-live-search/register';
import './component/sw-settings-search-live-search-keyword/register';
import './component/sw-settings-search-search-behaviour/register';
import './component/sw-settings-search-search-index/register';
import './component/sw-settings-search-searchable-content/register';
import './component/sw-settings-search-searchable-content-customfields/register';
import './component/sw-settings-search-searchable-content-general/register';
import './page/sw-settings-search/register';
import './view/sw-settings-search-view-general/register';
import './view/sw-settings-search-view-live-search/register';

const { Module } = Shopware;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-search', {
    type: 'core',
    name: 'settings-product-search-config',
    title: 'sw-settings-search.general.mainMenuItemGeneral',
    description: 'sw-settings-snippet.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-search',
    favicon: 'icon-module-settings.svg',
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
        group: 'general',
        to: 'sw.settings.search.index',
        icon: 'regular-search',
        privilege: 'product_search_config.viewer',
    },
});
