/**
 * @sw-package after-sales
 */
// eslint-disable-next-line sw-core-rules/mini-module-boundaries -- a bare import that registers nothing, as before the module moved
import './page/index/private';
import './page/index/register';

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
