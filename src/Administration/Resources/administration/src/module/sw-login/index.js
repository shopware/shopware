import './src/component/page/sw-login';

Shopware.Module.register('sw-login', {
    type: 'core',
    name: 'Core Login Module',
    description: 'Enter description here...',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#F19D12',

    routes: {
        index: {
            component: 'sw-login',
            path: 'index',
            alias: 'signin'
        }
    },

    navigation: {
        root: [{
            'sw.login.index': {
                icon: 'enter',
                color: '#F19D12',
                name: 'Login'
            }
        }]
    }
});
