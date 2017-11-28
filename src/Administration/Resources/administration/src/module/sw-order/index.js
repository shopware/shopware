import './src/component';

Shopware.Module.register('sw-order', {
    type: 'core',
    name: 'Bestellübersicht',
    description: 'Enter description here...',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#CA8EE0',
    icon: 'cart',

    routes: {
        index: {
            components: {
                default: 'sw-order-list'
            },
            path: 'index'
        },
        detail: {
            component: 'sw-order-detail',
            path: 'detail/:uuid',
            meta: {
                parentPath: 'sw.order.index'
            }
        }
    },

    navigation: {
        root: [{
            'sw.order.index': {
                icon: 'cart',
                color: '#CA8EE0',
                name: 'Bestellübersicht'
            }
        }]
    },

    commands: [{
        title: 'Übersicht',
        route: 'order.index'
    }, {
        title: '%0 öffnen',
        route: 'order.detail'
    }],

    shortcuts: {
        index: {
            mac: {
                title: 'order.index.shortcut.mac',
                combination: [
                    'CMD',
                    'O'
                ]
            },
            win: {
                title: 'order.index.shortcut.win',
                combination: [
                    'CTRL',
                    'O'
                ]
            }
        }
    }
});
