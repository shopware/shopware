/**
 * @package admin
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default [
    {
        path: '/',
        name: 'core',
        coreRoute: true,
        root: true,
        component: 'sw-desktop',
        redirect: '/sw/dashboard/index',
    },
    {
        path: '/error',
        name: 'error',
        coreRoute: true,
        component: 'sw-error',
        meta: {
            forceRoute: true,
        },
    },
];
