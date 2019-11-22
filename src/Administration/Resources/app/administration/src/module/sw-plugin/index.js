import './mixin/plugin-error-handler.mixin';
import './page/sw-plugin-manager';
import './view/sw-plugin-list';
import './view/sw-plugin-license-list';
import './view/sw-plugin-updates';
import './view/sw-plugin-recommendation';
import './component/sw-plugin-file-upload';
import './component/sw-plugin-store-login';
import './component/sw-plugin-store-login-status';
import './component/sw-plugin-updates-grid';
import './component/sw-plugin-last-updates-grid';
import './component/sw-plugin-table-entry';
import './extension/sw-settings-index';
import './component/sw-plugin-config';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('sw-plugin', {
    type: 'core',
    name: 'plugin',
    title: 'sw-plugin.general.mainMenuItemGeneral',
    description: 'sw-plugin.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'plugin',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            components: {
                default: 'sw-plugin-manager'
            },
            redirect: {
                name: 'sw.plugin.index.list'
            },
            path: 'index',
            children: {
                list: {
                    component: 'sw-plugin-list',
                    path: 'list',
                    meta: {
                        parentPath: 'sw.settings.index'
                    }
                },
                licenses: {
                    component: 'sw-plugin-license-list',
                    path: 'licenses',
                    meta: {
                        parentPath: 'sw.settings.index'
                    }
                },
                recommendations: {
                    component: 'sw-plugin-recommendation',
                    path: 'recommendations',
                    meta: {
                        parentPath: 'sw.settings.index'
                    }
                },
                updates: {
                    component: 'sw-plugin-updates',
                    path: 'updates',
                    meta: {
                        parentPath: 'sw.settings.index'
                    }
                }
            }
        },
        settings: {
            component: 'sw-plugin-config',
            path: 'settings/:namespace',
            meta: {
                parentPath: 'sw.plugin.index'
            }
        }
    }
});
