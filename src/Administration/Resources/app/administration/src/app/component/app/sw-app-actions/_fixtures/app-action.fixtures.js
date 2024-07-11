/**
 * @package admin
 */

import { createRouter as createRouterVue, createWebHistory } from 'vue-router'

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function createRouter() {
    return createRouterVue({
        routes: [
            {
                name: 'index',
                path: '/',
                component: {},
            },
            {
                name: 'sw.product.detail',
                path: '/sw/product/detail/:{id}',
                component: {},
                meta: {
                    $module: {
                        entity: 'product',
                    },
                    appSystem: {
                        view: 'detail',
                    },
                },
            }, {
                name: 'sw.product.list',
                path: '/sw/product/list',
                component: {},
                meta: {
                    $module: {
                        entity: 'product',
                    },
                    appSystem: {
                        view: 'list',
                    },
                },
            }, {
                name: 'sw.order.detail',
                path: '/sw/order/detail',
                component: {},
                meta: {
                    $module: {
                        entity: 'order',
                    },
                    appSystem: {
                        view: 'list',
                    },
                },
            }, {
                name: 'sw.settings.index',
                path: '/sw/setting/index',
                component: {},
                meta: {
                    $module: {},
                },
            }
        ],
        history: createWebHistory(),
    });
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const actionButtonData = [{
    id: Shopware.Utils.createId(),
    action: 'addProduct',
    app: 'TestApp',
    icon: 'someBase64Icon',
    label: {
        'de-DE': 'Product hinzufügen',
        'en-GB': 'Add product',
    },
    url: 'http://test-url/actions/product/add',
}, {
    id: Shopware.Utils.createId(),
    action: 'renameProduct',
    app: 'TestApp',
    icon: 'someBase64Icon',
    label: {
        'de-DE': 'Product umbenennen',
        'en-GB': 'Rename product',
    },
    url: 'http://test-url/actions/product/rename',
}];

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const actionResultData = {
    data: {
        actionType: 'notification',
        status: 'success',
        message: 'This is successful',
    },
};
