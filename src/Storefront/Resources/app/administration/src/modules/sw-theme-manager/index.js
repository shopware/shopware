import './mixin/sw-theme.mixin';
import './page/sw-theme-manager-detail';
import './page/sw-theme-manager-list';
import './component/sw-theme-list-item/';
import './component/sw-theme-modal/';

const { Module } = Shopware;

Module.register('sw-theme-manager', {
    type: 'core',
    title: 'sw-theme-manager.general.mainMenuItemGeneral',
    description: 'sw-theme-manager.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#ff68b4',
    icon: 'default-symbol-content',
    favicon: 'icon-module-content.png',
    entity: 'theme',

    routes: {
        index: {
            component: 'sw-theme-manager-list',
            path: 'index'
        },
        create: {
            component: 'sw-theme-manager-create',
            path: 'create',
            meta: {
                parentPath: 'sw.theme.manager.index'
            }
        },
        detail: {
            component: 'sw-theme-manager-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.theme.manager.index'
            }
        }
    },

    navigation: [{
        id: 'sw-theme-manager',
        label: 'sw-theme-manager.general.mainMenuItemGeneral',
        color: '#ff68b4',
        icon: 'default-object-image',
        path: 'sw.theme.manager.index',
        position: 80,
        parent: 'sw-content'
    }],

    // Add theme route to sales channel
    routeMiddleware(next, currentRoute) {
        if (currentRoute.name === 'sw.sales.channel.detail') {
            currentRoute.children.push({
                component: 'sw-sales-channel-detail-theme',
                name: 'sw.sales.channel.detail.theme',
                isChildren: true,
                path: '/sw/sales/channel/detail/:id/theme'
            });
        }

        next(currentRoute);
    }
});
