/**
 * @sw-package framework
 */
import './acl';

/** @private */
Shopware.Component.register('sw-oauth-client-list', () => import('./page/sw-oauth-client-list'));

/** @private */
Shopware.Module.register('sw-oauth-client', {
    type: 'core',
    name: 'oauth-client',
    title: 'sw-oauth-client.title',
    description: 'Manage public OAuth applications.',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-key',
    entity: 'oauth_client',
    routes: {
        index: {
            component: 'sw-oauth-client-list',
            path: 'index',
            meta: { parentPath: 'sw.settings.index.system', privilege: 'oauth_client.viewer' },
        },
    },
    settingsItem: {
        group: 'system',
        to: 'sw.oauth.client.index',
        icon: 'regular-key',
        privilege: 'oauth_client.viewer',
    },
});
