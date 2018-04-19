import dashboard from 'module/core.dashboard/src/sw-dashboard';

export default {
    id: 'core.dashboard',
    name: 'Core Dashboard Module',
    description: 'Enter description here...',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#dd4800',

    routes: {
        index: {
            component: dashboard,
            path: 'dashboard'
        }
    }
};
