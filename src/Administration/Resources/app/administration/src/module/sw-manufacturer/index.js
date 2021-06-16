import './page/sw-manufacturer-list';
import './page/sw-manufacturer-detail';
import './acl';

const { Module } = Shopware;

Module.register('sw-manufacturer', {
    type: 'core',
    name: 'manufacturer',
    title: 'sw-manufacturer.general.mainMenuItemGeneral',
    description: 'Manages the manufacturer of the application',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#57D9A3',
    icon: 'default-symbol-products',
    favicon: 'icon-module-products.png',
    entity: 'product_manufacturer',

    routes: {
        index: {
            components: {
                default: 'sw-manufacturer-list',
            },
            path: 'index',
            meta: {
                privilege: 'product_manufacturer.viewer',
            },
        },
        create: {
            component: 'sw-manufacturer-detail',
            path: 'create',
            meta: {
                parentPath: 'sw.manufacturer.index',
                privilege: 'product_manufacturer.creator',
            },
        },
        detail: {
            component: 'sw-manufacturer-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.manufacturer.index',
                privilege: 'product_manufacturer.viewer',
            },
            props: {
                default(route) {
                    return {
                        manufacturerId: route.params.id,
                    };
                },
            },
        },
    },

    navigation: [{
        path: 'sw.manufacturer.index',
        privilege: 'product_manufacturer.viewer',
        label: 'sw-manufacturer.general.mainMenuItemList',
        id: 'sw-manufacturer',
        parent: 'sw-catalogue',
        color: '#57D9A3',
        position: 50,
    }],
});
