import productList from 'module/core.product/src/list';
import productSidebar from 'module/core.product/src/sidebar';

export default {
    id: 'core.product',
    name: 'Core Product Module',
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
