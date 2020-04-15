import './page/sw-settings-google-shopping';
import { NEXT6050 } from 'src/flag/feature_next6050';

const { Module } = Shopware;

Module.register('sw-settings-google-shopping', {
    type: 'core',
    name: 'settings-google-shopping',
    title: 'sw-settings-google-shopping.general.mainMenuItemGeneral',
    flag: NEXT6050,
    description: 'sw-settings-google-shopping.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'store_settings',

    routes: {
        index: {
            component: 'sw-settings-google-shopping',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        }
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.google.shopping.index',
        icon: 'default-shopping-paper-bag'
    }
});
