/**
 * @sw-package fundamentals@framework
 */
import './acl';
import './acl/oauth-client';

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Component.register('sw-integration-list', () => import('./page/sw-integration-list'));

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Component.register('sw-integration-mcp-allowlist', () => import('./component/sw-integration-mcp-allowlist'));

/** @private */
Shopware.Component.register('sw-oauth-client-list', () => import('./page/sw-oauth-client-list'));

/** @private */
Shopware.Component.register('sw-integration-tabs', () => import('./component/sw-integration-tabs'));

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-integration', {
    type: 'core',
    name: 'integration',
    title: 'sw-integration.general.mainMenuItemIndex',
    description: 'The module for managing integrations.',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-key',
    favicon: 'icon-module-settings.svg',
    entity: 'integration',

    routes: {
        index: {
            component: 'sw-integration-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index.system',
                privilege: 'integration.viewer',
            },
        },
        oauth: {
            component: 'sw-oauth-client-list',
            path: 'oauth',
            meta: {
                parentPath: 'sw.settings.index.system',
                privilege: 'oauth_client.viewer',
            },
        },
    },

    settingsItem: {
        group: 'system',
        // Resolve at render time so OAuth-only roles can use the same Settings entry.
        get to() {
            return Shopware.Service('acl').can('integration.viewer') ? 'sw.integration.index' : 'sw.integration.oauth';
        },
        icon: 'regular-key',
        get privilege() {
            return Shopware.Service('acl').can('integration.viewer') ? 'integration.viewer' : 'oauth_client.viewer';
        },
    },
});
