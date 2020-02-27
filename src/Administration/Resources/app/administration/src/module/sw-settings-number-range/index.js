import './page/sw-settings-number-range-list';
import './page/sw-settings-number-range-detail';
import './page/sw-settings-number-range-create';

const { Module } = Shopware;

Module.register('sw-settings-number-range', {
    type: 'core',
    name: 'settings-number-range',
    title: 'sw-settings-number-range.general.mainMenuItemGeneral',
    description: 'Number Range section in the settings module',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'number_range',

    routes: {
        index: {
            component: 'sw-settings-number-range-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        },
        detail: {
            component: 'sw-settings-number-range-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.number.range.index'
            }
        },
        create: {
            component: 'sw-settings-number-range-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.number.range.index'
            }
        }
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.settings.number.range.index',
        icon: 'default-documentation-paper-pencil-signed'
    }
});
