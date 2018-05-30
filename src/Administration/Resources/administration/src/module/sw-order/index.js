import { Module } from 'src/core/shopware';
import './page/sw-order-list';

Module.register('sw-order', {
    type: 'core',
    name: 'Orders',
    description: 'sw-order.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#A092F0',
    icon: 'default-shopping-paper-bag',

    routes: {
        index: {
            components: {
                default: 'sw-order-list',
                sidebar: 'sw-order-sidebar'
            },
            path: 'index/:offset?/:limit?/:sortBy?/:sortDirection?/:term?/:filters?'
        }
    },

    navigation: [{
        id: 'sw-order',
        label: 'sw-order.general.mainMenuItemGeneral',
        color: '#A092F0',
        icon: 'default-shopping-paper-bag'
    }, {
        path: 'sw.order.index',
        label: 'sw-order.general.mainMenuItemList',
        parent: 'sw-order'
    }]
});
