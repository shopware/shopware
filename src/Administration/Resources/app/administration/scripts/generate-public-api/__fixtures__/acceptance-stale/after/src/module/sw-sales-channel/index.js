/**
 * @sw-package discovery
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-sales-channel-detail', () => import('./page/sw-sales-channel-detail'));
Shopware.Component.extend(
    'sw-sales-channel-create',
    'sw-sales-channel-detail',
    () => import('./page/sw-sales-channel-create'),
);
