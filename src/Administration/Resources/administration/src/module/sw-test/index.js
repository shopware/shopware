import './src/component/page/extend';
import './src/component/page/override';
import './src/component/page/overrideExtension';

Shopware.Module.register('sw-test', {
    type: 'core',
    name: 'Test Module',
    description: 'This module is just for testing the inheritance.',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#ea5b0c',
    icon: 'brush',

    routes: {
        index: {
            components: {
                default: 'sw-test-extend'
            },
            path: 'extend'
        }
    },

    navigation: {
        root: [{
            'sw.test.index': {
                icon: 'brush',
                color: '#ea5b0c',
                name: 'Extension Test'
            }
        }]
    }
});
