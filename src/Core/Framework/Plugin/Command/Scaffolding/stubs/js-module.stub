import './page/swag-example-list';

Shopware.Module.register('swag-example', {
    type: 'plugin',
    name: 'Example',
    title: 'swag-example.general.mainMenuItemGeneral',
    description: 'sw-property.general.descriptionTextModule',
    color: '#ff3d58',
    icon: 'default-shopping-paper-bag-product',

    routes: {
        list: {
            component: 'swag-example-list',
            path: 'list'
        },
    },

    navigation: [{
        parent: 'sw-catalogue',
        label: 'swag-example.general.mainMenuItemGeneral',
        color: '#ff3d58',
        path: 'swag.example.list',
        icon: 'default-shopping-paper-bag-product',
        position: 100
    }]
});
