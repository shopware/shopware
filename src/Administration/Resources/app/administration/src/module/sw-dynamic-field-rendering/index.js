// eslint-disable-next-line sw-core-rules/require-package-annotation,sw-deprecation-rules/private-feature-declarations
Shopware.Component.register('sw-dynamic-field-rendering', () => import('./page/sw-dynamic-field-rendering/index'));

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Module.register('sw-dynamic-field-rendering', {
    type: 'core',
    name: 'dynamic-field-rendering',
    title: 'sw-dynamic-field-rendering.general.mainMenuItemGeneral',
    description: 'Just a debugging page for dynamic field rendering',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#57D9A3',
    icon: 'solid-products',
    favicon: 'icon-module-products.png',

    routes: {
        index: {
            components: {
                default: 'sw-dynamic-field-rendering',
            },
            path: 'index',
            meta: {
                privilege: 'product_manufacturer.viewer',
            },
        },
    },

    navigation: [
        {
            path: 'sw.dynamic.field.rendering.index',
            label: 'sw-dynamic-field-rendering.general.mainMenuItemList',
            id: 'sw-dynamic-field-rendering',
            parent: 'sw-catalogue',
            color: '#57D9A3',
            position: 50,
        },
    ],
});
