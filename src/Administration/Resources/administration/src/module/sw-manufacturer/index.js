import { Module } from 'src/core/shopware';
import './page/sw-manufacturer-list';

Module.register('sw-manufacturer', {
    type: 'core',
    name: 'Manufacturer',
    description: 'Manages the manufacturer of the application',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#57D9A3',
    icon: 'default-symbol-products',

    routes: {
        index: {
            components: {
                default: 'sw-manufacturer-list'
            },
            path: 'index/:offset?/:limit?/:sortBy?/:sortDirection?/:term?/:filters?'
        }
    },

    navigation: [{
        path: 'sw.manufacturer.index',
        label: 'sw-manufacturer.general.mainMenuItemList',
        parent: 'sw.product.index',
        color: '#57D9A3'
    }]
});
