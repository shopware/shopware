export default [
    {
        path: '/core',
        alias: '/',
        name: 'core',
        coreRoute: true,
        root: true,
        component: 'sw-desktop',
        redirect: '/sw/dashboard/index'
    }
];
