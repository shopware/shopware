/** @private */
Shopware.Component.register('sw-login-v2', () => import('./page/index'));
/** @private */
Shopware.Component.register('sw-login-login-v2', () => import('./view/sw-login-login-v2'));
/** @private */
Shopware.Component.register('sw-login-access-denied-v2', () => import('./view/sw-login-access-denied-v2'));
/** @private */
Shopware.Component.register('sw-login-recovery-v2', () => import('./view/sw-login-recovery-v2'));
/** @private */
Shopware.Component.register('sw-login-recovery-info-v2', () => import('./view/sw-login-recovery-info-v2'));
/** @private */
Shopware.Component.register('sw-login-recovery-recovery-v2', () => import('./view/sw-login-recovery-recovery-v2'));

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
            component: 'sw-login-v2',
            coreRoute: true,
            redirect: {
                name: 'sw.login.v2.index.credentials',
            },
            children: {
                credentials: {
                    component: 'sw-login-login-v2',
                    path: '',
                },
                accessDenied: {
                    component: 'sw-login-access-denied-v2',
                    path: 'access-denied',
                },
                recovery: {
                    component: 'sw-login-recovery-v2',
                    path: 'recovery',
                    meta: {
                        backToLogin: true,
                    },
                },
                requestSent: {
                    component: 'sw-login-recovery-info-v2',
                    path: 'request-sent',
                    meta: {
                        backToLogin: true,
                    },
                },
                reset: {
                    component: 'sw-login-recovery-recovery-v2',
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
