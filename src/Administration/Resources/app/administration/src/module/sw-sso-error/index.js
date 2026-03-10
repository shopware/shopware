/**
 * @sw-package after-sales
 */
import './page/index';

/**
 * @private
 */
Shopware.Component.register('sw-sso-error-index', () => import('./page/index'));

const { Module } = Shopware;

/**
 * @private
 */
Module.register('sw-sso-error', {
    type: 'core',
    name: 'sso-error',
    title: 'global.sw-sso-error.general.title',
    description: 'global.sw-sso-error.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#f1122c',

    routes: {
        index: {
            coreRoute: true,
            component: 'sw-sso-error-index',
            path: '/sso/error',
        },
    },
});
