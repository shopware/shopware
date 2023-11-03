/* eslint-disable max-len, sw-deprecation-rules/private-feature-declarations */

Shopware.Component.register('sw-dashboard-statistics', () => import('./component/sw-dashboard-statistics'));
Shopware.Component.register('sw-dashboard-index', () => import('./page/sw-dashboard-index'));
/* eslint-enable max-len, sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line max-len,sw-deprecation-rules/private-feature-declarations
Shopware.Component.register('sw-usage-data-consent-banner', () => import('src/module/sw-settings-usage-data/component/sw-usage-data-consent-banner'));

/**
 * @package services-settings
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
    color: '#6AD6F0',
    icon: 'regular-tachometer',
    favicon: 'icon-module-dashboard.png',

    routes: {
        index: {
            components: {
                default: 'sw-dashboard-index',
            },
            path: 'index',
        },
    },

    navigation: [{
        id: 'sw-dashboard',
        label: 'sw-dashboard.general.mainMenuItemGeneral',
        color: '#6AD6F0',
        icon: 'regular-tachometer',
        path: 'sw.dashboard.index',
        position: 10,
    }],
});
