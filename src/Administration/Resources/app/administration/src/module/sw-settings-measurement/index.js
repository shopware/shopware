/**
 * @sw-package inventory
 */
import './acl';

/* eslint-disable max-len, sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-settings-measurement', () => import('./page/sw-settings-measurement'));
Shopware.Component.register(
    'sw-settings-measurement-default-units',
    () => import('./component/sw-settings-measurement-default-units'),
);
/* eslint-enable max-len, sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Module.register('sw-settings-measurement', {
    type: 'core',
    name: 'settings-measurement',
    title: 'sw-settings-measurement.general.mainMenuItemGeneral',
    description: 'sw-settings-measurement.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',

    routes: {
        index: {
            component: 'sw-settings-measurement',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'general',
        to: 'sw.settings.measurement.index',
        icon: 'regular-balance-scale',
        privilege: 'system.system_config',
    },
});
