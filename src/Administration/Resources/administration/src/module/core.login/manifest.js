import login from 'module/core.login/src/view/sw-login/sw-login';

export default {
    id: 'core.login',
    name: 'Core Login Module',
    description: 'Enter description here...',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#F19D12',

    routes: {
        index: {
            component: login,
            path: 'index',
            alias: 'signin'
        }
    },

    navigation: {
        root: [{
            'core.login.index': {
                icon: 'enter',
                color: '#F19D12',
                name: 'Login'
            }
        }]
    }
};
