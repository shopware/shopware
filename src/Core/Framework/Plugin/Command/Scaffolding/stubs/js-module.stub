// <plugin root>/src/Resources/app/administration/src/module/swag-example/index.js

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
        detail: {
            component: 'swag-example-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'swag.example.list'
            }
        },
        create: {
            component: 'swag-example-create',
            path: 'create',
            meta: {
                parentPath: 'swag.example.list'
            }
        }
    },

    navigation: [{
        label: 'swag-example.general.mainMenuItemGeneral',
        color: '#ff3d58',
        path: 'swag.example.list',
        icon: 'default-shopping-paper-bag-product',
        position: 100
    }]
});
