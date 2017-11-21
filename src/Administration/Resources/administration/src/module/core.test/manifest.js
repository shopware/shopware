import extendComp from './src/components/page/extend';
import './src/components/page/override';
import './src/components/page/overrideExtension';

export default {
    id: 'core.test',
    name: 'Test Module',
    description: 'This module is just for testing the inheritance.',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#ea5b0c',
    icon: 'brush',

    routes: {
        index: {
            components: {
                default: extendComp
            },
            path: 'extend'
        }
    },

    navigation: {
        root: [{
            'core.test.index': {
                icon: 'brush',
                color: '#ea5b0c',
                name: 'Extension Test'
            }
        }]
    }
};
