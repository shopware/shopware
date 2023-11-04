/**
 * @package sales-channel
 */

/* eslint-disable max-len, sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-seo-url-template-card', () => import('./component/sw-seo-url-template-card'));
Shopware.Component.register('sw-seo-url', () => import('./component/sw-seo-url'));
Shopware.Component.register('sw-seo-main-category', () => import('./component/sw-seo-main-category'));
Shopware.Component.register('sw-settings-seo', () => import('./page/sw-settings-seo'));
/* eslint-enable max-len, sw-deprecation-rules/private-feature-declarations */

const { Module } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-seo', {
    type: 'core',
    name: 'settings-seo',
    title: 'sw-settings-seo.general.mainMenuItemGeneral',
    description: 'SEO section in the settings module',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',
    entity: 'seo',

    routes: {
        index: {
            component: 'sw-settings-seo',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.seo.index',
        icon: 'regular-search',
        privilege: 'system.system_config',
    },
});
