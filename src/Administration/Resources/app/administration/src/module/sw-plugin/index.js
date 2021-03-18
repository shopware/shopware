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

/**
 * @feature-deprecated (flag:FEATURE_NEXT_12608) tag:v6.4.0
 * Deprecation notice: The whole plugin manager will be removed with 6.4.0 and replaced
 * by the extension module.
 * When removing the feature flag for FEATURE_NEXT_12608, also merge the merge request
 * for NEXT-13821 which removes the plugin manager.
 */

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
                        parentPath: 'sw.settings.index',
                        privilege: 'system.plugin_maintain'
                    }
                },
                licenses: {
                    component: 'sw-plugin-license-list',
                    path: 'licenses',
                    meta: {
                        parentPath: 'sw.settings.index',
                        privilege: 'system.plugin_maintain'
                    }
                },
                recommendations: {
                    component: 'sw-plugin-recommendation',
                    path: 'recommendations',
                    meta: {
                        parentPath: 'sw.settings.index',
                        privilege: 'system.plugin_maintain'
                    }
                },
                updates: {
                    component: 'sw-plugin-updates',
                    path: 'updates',
                    meta: {
                        parentPath: 'sw.settings.index',
                        privilege: 'system.plugin_maintain'
                    }
                }
            }
        },
        settings: {
            component: 'sw-plugin-config',
            path: 'settings/:namespace',
            meta: {
                parentPath: 'sw.plugin.index',
                privilege: 'system.plugin_maintain'
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
                parentPath: 'sw.plugin.index',
                privilege: 'system.plugin_maintain'
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
        privilege: 'system.plugin_maintain',
        group: 'system',
        to: 'sw.plugin.index',
        icon: 'default-object-plug'
    }
});
