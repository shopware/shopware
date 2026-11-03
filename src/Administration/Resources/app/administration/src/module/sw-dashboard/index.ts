import './component/sw-dashboard-statistics/register';
import './page/sw-dashboard-index/register';

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
    color: 'var(--sw-color-module-brand-default)',
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
            color: 'var(--sw-color-module-brand-default)',
            icon: 'regular-dashboard',
            path: 'sw.dashboard.index',
            position: 10,
        },
    ],
});
