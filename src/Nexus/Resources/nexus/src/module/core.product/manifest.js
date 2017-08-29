import productList from 'module/core.product/src/list';

export default {
    id: 'core.product',
    name: 'Core Product Module',
    description: 'Enter description here...',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#dd4800',

    routes: {
        index: {
            component: productList,
            path: 'product'
        }
    },

    navigation: {
        root: [{
            'core.product.index': {
                icon: 'box',
                color: '#dd4800',
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
