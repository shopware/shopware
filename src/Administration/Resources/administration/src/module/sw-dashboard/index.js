import './src/component/page/dashboard';

Shopware.Module.register('sw-dashboard', {
    type: 'core',
    name: 'Core Dashboard Module',
    description: 'Enter description here...',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#6abff0',

    routes: {
        index: {
            component: 'sw-dashboard',
            path: 'index'
        }
    },

    navigation: {
        root: [{
            'sw.dashboard.index': {
                icon: 'browser',
                color: '#6abff0',
                name: 'Dashboard'
            }
        }]
    }
});
