import productList from 'module/core.product/src/components/page/core-product-list';
import productDetail from 'module/core.product/src/components/page/core-product-detail';
import productCreate from 'module/core.product/src/components/page/core-product-create';
import productSidebar from 'module/core.product/src/components/organism/core-product-sidebar';
import 'module/core.product/src/components';

export default {
    id: 'core.product',
    name: 'Produkt Übersicht',
    description: 'Enter description here...',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9CD0C8',
    icon: 'box',

    routes: {
        index: {
            components: {
                default: productList,
                sidebar: productSidebar
            },
            path: 'product'
        },

        create: {
            component: productCreate,
            path: 'product/create',
            meta: {
                parentPath: 'core.product.index'
            }
        },

        detail: {
            component: productDetail,
            path: 'product/detail/:uuid',
            meta: {
                parentPath: 'core.product.index'
            }
        }
    },

    navigation: {
        root: [{
            'core.product.index': {
                icon: 'box',
                color: '#9CD0C8',
                name: 'Produktübersicht'
            }
        }]
    },

    commands: [{
        title: 'Übersicht',
        route: 'product.index'
    }, {
        title: '%0 öffnen',
        route: 'product.detail'
    }],

    shortcuts: {
        index: {
            mac: {
                title: 'product.index.shortcut.mac',
                combination: [
                    'CMD',
                    'P'
                ]
            },
            win: {
                title: 'product.index.shortcut.win',
                combination: [
                    'CTRL',
                    'P'
                ]
            }
        }
    }
};
