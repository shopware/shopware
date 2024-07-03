/**
 * @package innovation
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Component.register('sw-settings-media', () => import('./page/sw-settings-media'));

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-media', {
    type: 'core',
    name: 'settings-media',
    title: 'sw-settings-media.general.title',
    description: 'sw-settings-media.general.description',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',

    routes: {
        index: {
            component: 'sw-settings-media',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.media.index',
        icon: 'regular-image',
        privilege: 'system.system_config',
    },
});
