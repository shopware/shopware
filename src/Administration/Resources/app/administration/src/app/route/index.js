/**
 * @sw-package framework
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
        children: [
            {
                // Catch-all for unknown URLs, rendered inside `sw-desktop` to keep the main menu available
                path: ':pathMatch(.*)*',
                name: 'sw.not.found.index',
                component: 'sw-not-found',
            },
        ],
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
