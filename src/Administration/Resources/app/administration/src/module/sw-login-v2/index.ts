/** @private */
Shopware.Component.register('sw-login-v2-index', () => import('./page/sw-login-v2-index'));
/** @private */
Shopware.Component.register('sw-login-v2-credentials', () => import('./view/sw-login-v2-credentials'));

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
            },
        },
    },
});
