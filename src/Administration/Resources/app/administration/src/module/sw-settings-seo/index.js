import './component/sw-seo-url-template-card';
import './component/sw-seo-url';
import './component/sw-seo-main-category';

import './page/sw-settings-seo';

const { Module } = Shopware;

Module.register('sw-settings-seo', {
    type: 'core',
    name: 'settings-seo',
    title: 'sw-settings-seo.general.mainMenuItemGeneral',
    description: 'SEO section in the settings module',
    color: '#9AA8B5',
    icon: 'default-action-settings',
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
        icon: 'default-action-search',
        privilege: 'system.system_config',
    },
});
