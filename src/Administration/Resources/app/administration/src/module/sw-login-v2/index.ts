/** @private */
Shopware.Component.register('sw-login-v2-index', () => import('./page/sw-login-v2-index'));
/** @private */
Shopware.Component.register('sw-login-v2-credentials', () => import('./view/sw-login-v2-credentials'));
/** @private */
Shopware.Component.register('sw-login-v2-access-denied', () => import('./view/sw-login-v2-access-denied'));
/** @private */
Shopware.Component.register('sw-login-v2-recovery', () => import('./view/sw-login-v2-recovery'));
/** @private */
Shopware.Component.register('sw-login-v2-request-sent', () => import('./view/sw-login-v2-request-sent'));
/** @private */
Shopware.Component.register('sw-login-v2-reset', () => import('./view/sw-login-v2-reset'));

/**
 * @sw-package framework
 * @private
 */
Shopware.Module.register('sw-login-v2', {
    type: 'core',
    name: 'login-v2',
    title: 'sw-login-v2.general.moduleTitle',

    routes: {
        index: {
            path: '/login-v2',
            component: 'sw-login-v2-index',
            coreRoute: true,
            redirect: {
                name: 'sw.login.v2.index.credentials',
            },
            children: {
                credentials: {
                    component: 'sw-login-v2-credentials',
                    path: '',
                },
                accessDenied: {
                    component: 'sw-login-v2-access-denied',
                    path: 'access-denied',
                },
                recovery: {
                    component: 'sw-login-v2-recovery',
                    path: 'recovery',
                    meta: {
                        backToLogin: true,
                    },
                },
                requestSent: {
                    component: 'sw-login-v2-request-sent',
                    path: 'request-sent',
                    meta: {
                        backToLogin: true,
                    },
                },
                reset: {
                    component: 'sw-login-v2-reset',
                    path: 'reset/:hash',
                    props: true,
                    meta: {
                        backToLogin: true,
                    },
                },
            },
        },
    },
});
