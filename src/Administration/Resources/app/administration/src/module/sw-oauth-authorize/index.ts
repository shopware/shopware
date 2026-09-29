/**
 * @sw-package framework
 */

/** @private */
Shopware.Component.register('sw-oauth-authorize-index', () => import('./page/index'));

/**
 * Consent page of the OAuth2 authorization code flow. The backend forwards the browser
 * to this route after validating the authorization request of a client (e.g. the Shopware CLI).
 *
 * The route is intentionally a normal protected route: the router guard redirects
 * unauthenticated users to the login and restores the full path including the query afterwards.
 *
 * @private
 */
Shopware.Module.register('sw-oauth-authorize', {
    type: 'core',
    name: 'oauth-authorize',
    title: 'sw-oauth-authorize.general.title',
    description: 'sw-oauth-authorize.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#189EFF',

    routes: {
        index: {
            coreRoute: true,
            component: 'sw-oauth-authorize-index',
            path: '/oauth/authorize',
        },
    },
});
