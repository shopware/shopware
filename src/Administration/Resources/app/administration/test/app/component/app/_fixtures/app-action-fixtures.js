import VueRouter from 'vue-router';

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

export const actionButtonData = [{
    id: Shopware.Utils.createId(),
    action: 'addProduct',
    app: 'TestApp',
    icon: 'someBase64Icon',
    label: {
        'de-DE': 'Product hinzufügen',
        'en-GB': 'Add product',
    },
    /**
     * @feature-deprecated (FEATURE_NEXT_14360) tag:v6.5.0 - "openNewTab" key will be removed.
     * It will no longer be used in the manifest.xml file
     * and will be processed in the Executor with an OpenNewTabResponse response instead.
     */
    openNewTab: false,
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
    /**
     * @feature-deprecated (FEATURE_NEXT_14360) tag:v6.5.0 - "openNewTab" key will be removed.
     * It will no longer be used in the manifest.xml file
     * and will be processed in the Executor with an OpenNewTabResponse response instead.
     */
    openNewTab: false,
    url: 'http://test-url/actions/product/rename',
}];

export const actionResultData = {
    data: {
        actionType: 'notification',
        status: 'success',
        message: 'This is successful',
    },
};
