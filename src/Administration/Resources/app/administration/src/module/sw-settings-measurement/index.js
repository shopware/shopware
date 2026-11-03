/**
 * @sw-package inventory
 */
import './acl';
import './component/sw-settings-measurement-default-units/register';
import './page/sw-settings-measurement/register';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Module.register('sw-settings-measurement', {
    type: 'core',
    name: 'settings-measurement',
    title: 'sw-settings-measurement.general.mainMenuItemGeneral',
    description: 'sw-settings-measurement.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-balance-scale',
    favicon: 'icon-module-settings.svg',

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
