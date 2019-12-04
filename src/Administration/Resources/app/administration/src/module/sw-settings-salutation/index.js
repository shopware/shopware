import './extension/sw-settings-index';
import './page/sw-settings-salutation-list';
import './page/sw-settings-salutation-detail';

const { Module } = Shopware;

Module.register('sw-settings-salutation', {
    type: 'core',
    name: 'settings-salutation',
    title: 'sw-settings-salutation.general.mainMenuItemGeneral',
    description: 'sw-settings-snippet.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'salutation',

    routes: {
        index: {
            component: 'sw-settings-salutation-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        },
        detail: {
            component: 'sw-settings-salutation-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.salutation.index'
            },
            props: {
                default(route) {
                    return {
                        salutationId: route.params.id
                    };
                }
            }
        },
        create: {
            component: 'sw-settings-salutation-detail',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.salutation.index'
            }
        }
    }
});
