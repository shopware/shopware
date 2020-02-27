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
import './component/sw-plugin-config';
import './component/sw-plugin-description';

import swPluginState from './state/plugin.store';

const { Module, State } = Shopware;
State.registerModule('swPlugin', swPluginState);

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
            },

            props: {
                default(route) {
                    return { namespace: route.params.namespace };
                }
            }
        },
        description: {
            component: 'sw-plugin-description',
            path: 'description/:namespace',
            meta: {
                parentPath: 'sw.plugin.index'
            },

            props: {
                default(route) {
                    return {
                        namespace: route.params.namespace,
                        description: route.params.description
                    };
                }
            }
        }
    },

    settingsItem: {
        group: 'system',
        to: 'sw.plugin.index',
        icon: 'default-object-plug'
    }
});
