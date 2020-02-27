import './page/sw-settings-listing';

const { Module } = Shopware;

Module.register('sw-settings-listing', {
    type: 'core',
    name: 'settings-listing',
    title: 'sw-settings-listing.general.mainMenuItemGeneral',
    description: 'sw-settings-listing.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'store_settings',

    routes: {
        index: {
            component: 'sw-settings-listing',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        }
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.listing.index',
        icon: 'default-symbol-products'
    }
});
