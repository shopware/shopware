import './acl';
import './service';

/**
 * @sw-package framework
 */

/**
 * @private
 */
Shopware.Component.register('sw-settings-system-health-index', () => import('./page/sw-settings-system-health-index'));

/**
 * @private
 */
Shopware.Component.register(
    'sw-settings-system-health-check-row',
    () => import('./component/sw-settings-system-health-check-row'),
);

/**
 * @private
 */
Shopware.Component.register(
    'sw-settings-system-health-check-details',
    () => import('./component/sw-settings-system-health-check-details'),
);

/**
 * @private
 */
Shopware.Component.register(
    'sw-settings-system-health-check-default',
    () => import('./component/sw-settings-system-health-check-default'),
);

/**
 * @private
 */
Shopware.Component.register(
    'sw-settings-system-health-section',
    () => import('./component/sw-settings-system-health-section'),
);

/**
 * @private
 */
Shopware.Component.register(
    'sw-settings-system-health-values',
    () => import('./component/sw-settings-system-health-values'),
);

/**
 * @private
 */
Shopware.Component.register(
    'sw-settings-system-health-request',
    () => import('./component/sw-settings-system-health-request'),
);

/**
 * @private
 */
Shopware.Component.register(
    'sw-settings-system-health-response-code',
    () => import('./component/sw-settings-system-health-response-code'),
);

/**
 * @private
 */
Shopware.Component.register('sw-settings-system-health-text', () => import('./component/sw-settings-system-health-text'));

/**
 * @private
 */
Shopware.Component.register(
    'sw-settings-system-health-app-url',
    () => import('./component/sw-settings-system-health-app-url'),
);

/**
 * @private
 */
Shopware.Component.register(
    'sw-settings-system-health-administration',
    () => import('./component/sw-settings-system-health-administration'),
);

/**
 * @private
 */
Shopware.Component.register(
    'sw-settings-system-health-storefront-pages',
    () => import('./component/sw-settings-system-health-storefront-pages'),
);

/**
 * @private
 */
Shopware.Component.register(
    'sw-settings-system-health-locales',
    () => import('./component/sw-settings-system-health-locales'),
);

/**
 * @private
 */
Shopware.Component.register(
    'sw-settings-system-health-shop-id',
    () => import('./component/sw-settings-system-health-shop-id'),
);

/**
 * @private
 */
Shopware.Module.register('sw-settings-system-health', {
    type: 'core',
    name: 'system-health',
    title: 'sw-settings-system-health.general.title',
    description: 'sw-settings-system-health.general.description',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-heart',
    favicon: 'icon-module-settings.svg',

    routes: {
        index: {
            component: 'sw-settings-system-health-index',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index.system',
                privilege: 'system.system_health',
            },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'sw.settings.system.health.index',
        icon: 'regular-heart',
        privilege: 'system.system_health',
    },
});
