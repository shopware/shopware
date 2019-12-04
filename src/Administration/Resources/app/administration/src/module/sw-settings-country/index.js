import './extension/sw-settings-index';
import './page/sw-settings-country-list';
import './page/sw-settings-country-detail';
import './page/sw-settings-country-create';
import './component/sw-country-state-detail';

const { Module } = Shopware;

Module.register('sw-settings-country', {
    type: 'core',
    name: 'settings-country',
    title: 'sw-settings-country.general.mainMenuItemGeneral',
    description: 'Country section in the settings module',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'country',

    routes: {
        index: {
            component: 'sw-settings-country-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        },
        detail: {
            component: 'sw-settings-country-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.country.index'
            }
        },
        create: {
            component: 'sw-settings-country-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.country.index'
            }
        }
    }
});
