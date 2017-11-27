import dashboard from 'module/core.dashboard/src/sw-dashboard';

export default {
    id: 'core.dashboard',
    name: 'Core Dashboard Module',
    description: 'Enter description here...',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#6abff0',

    routes: {
        index: {
            component: dashboard,
            path: 'index'
        }
    },

    navigation: {
        root: [{
            'core.dashboard.index': {
                icon: 'browser',
                color: '#6abff0',
                name: 'Dashboard'
            }
        }]
    }
};
