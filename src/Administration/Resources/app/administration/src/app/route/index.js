export default [
    {
        path: '/core',
        alias: '/',
        name: 'core',
        coreRoute: true,
        root: true,
        component: 'sw-desktop',
        redirect: '/sw/dashboard/index'
    },
    {
        path: '/error',
        name: 'error',
        coreRoute: true,
        component: 'sw-error',
        meta: {
            forceRoute: true
        }
    }
];
