/**
 * @sw-package innovation
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Component.register('sw-settings-media', () => import('./page/sw-settings-media'));

const { Module, Feature } = Shopware;

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
        group: function () {
            // @deprecated tag:v6.7.0 - Remove condition and function callback
            if (!Feature.isActive('v6.7.0.0')) {
                return 'shop';
            }

            return 'content';
        },
        to: 'sw.settings.media.index',
        icon: 'regular-image',
        privilege: 'system.system_config',
    },
});
