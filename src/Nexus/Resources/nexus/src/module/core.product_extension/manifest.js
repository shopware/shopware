import productListExtension from 'module/core.product_extension/src/list';
import overrides from 'module/core.product_extension/src/overrides';

overrides.initOverrides();

export default {
    id: 'core.product.extension',
    name: 'Core Product Module Extension',
    description: 'This is an extension of the existing core product module',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#306cbe',

    routes: {
        index: {
            component: productListExtension,
            path: 'extension'
        }
    },

    navigation: {
        root: [{
            'core.product.extension.index': {
                icon: 'box',
                color: '#306cbe',
                name: 'Product Extension'
            }
        }]
    }
};
