/**
 * @package admin
 */

import VueRouter from 'vue-router';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function createRouter() {
    return new VueRouter({
        routes: [{
            name: 'sw.product.detail',
            path: '/sw/product/detail/:{id}',
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
            meta: {
                $module: {},
            },
        }],
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
