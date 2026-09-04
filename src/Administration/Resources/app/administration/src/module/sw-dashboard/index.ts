/* eslint-disable sw-deprecation-rules/private-feature-declarations */

Shopware.Component.register('sw-dashboard-statistics', () => import('./component/sw-dashboard-statistics'));
Shopware.Component.register('sw-dashboard-index', () => import('./page/sw-dashboard-index'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

/**
 * @sw-package after-sales
 *
 * @private
 */
Shopware.Module.register('sw-dashboard', {
    type: 'core',
    name: 'dashboard',
    title: 'sw-dashboard.general.mainMenuItemGeneral',
    description: 'sw-dashboard.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--color-module-brand-default)',
    icon: 'regular-dashboard',
    favicon: 'icon-module-dashboard.svg',

    routes: {
        index: {
            components: {
                default: 'sw-dashboard-index',
            },
            path: 'index',
        },
    },

    navigation: [
        {
            id: 'sw-dashboard',
            label: 'sw-dashboard.general.mainMenuItemGeneral',
            color: 'var(--color-module-brand-default)',
            icon: 'regular-dashboard',
            path: 'sw.dashboard.index',
            position: 10,
        },
    ],
});
