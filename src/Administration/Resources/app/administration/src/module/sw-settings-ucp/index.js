/**
 * @sw-package fundamentals@framework
 *
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Admin module for managing the Universal Commerce Protocol (UCP) configuration
 * per Sales Channel: capabilities, transports, signing keys, profile preview,
 * platform-profile cache.
 */
import './acl';

const { Module, Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-settings-ucp-index', () => import('./page/sw-settings-ucp-index'));
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-settings-ucp-detail', () => import('./page/sw-settings-ucp-detail'));

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-ucp', {
    type: 'core',
    name: 'settings-ucp',
    title: 'sw-settings-ucp.general.mainMenuItemGeneral',
    description: 'sw-settings-ucp.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'regular-globe',
    favicon: 'icon-module-settings.png',
    entity: 'sales_channel',

    routes: {
        index: {
            component: 'sw-settings-ucp-index',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index.system',
                privilege: 'ucp.viewer',
            },
        },
        detail: {
            component: 'sw-settings-ucp-detail',
            path: 'detail/:salesChannelId',
            props: {
                default: (route) => ({ salesChannelId: route.params.salesChannelId }),
            },
            meta: {
                parentPath: 'sw.settings.ucp.index',
                privilege: 'ucp.viewer',
            },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'sw.settings.ucp.index',
        icon: 'regular-globe',
        privilege: 'ucp.viewer',
        // Hide entry when feature flag is off
        flag: 'UCP_SERVER',
    },
});
