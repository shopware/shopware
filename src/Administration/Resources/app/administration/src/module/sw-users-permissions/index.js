import './page/sw-users-permissions';
import './components/sw-users-permissions-user-listing';
import './components/sw-users-permissions-role-listing';
import './components/sw-users-permissions-additional-permissions';
import './components/sw-users-permissions-permissions-grid';
import './page/sw-users-permissions-user-detail';
import './page/sw-users-permissions-user-create';
import './page/sw-users-permissions-role-detail';

Shopware.Module.register('sw-users-permissions', {
    type: 'core',
    name: 'users-permissions',
    title: 'sw-users-permissions.general.label',
    description: 'sw-users-permissions.general.label',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'user',

    routes: {
        index: {
            component: 'sw-users-permissions',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        },
        'user.detail': {
            // @deprecated tag:v6.4.0 - use 'sw-users-permissions-user-detail' instead
            component: 'sw-settings-user-detail',
            path: 'user.detail/:id?',
            meta: {
                parentPath: 'sw.users.permissions.index'
            }
        },
        'user.create': {
            // @deprecated tag:v6.4.0 - use 'sw-users-permissions-user-create' instead
            component: 'sw-settings-user-create',
            path: 'user.create',
            meta: {
                parentPath: 'sw.users.permissions.index'
            }
        },
        'role.detail': {
            component: 'sw-users-permissions-role-detail',
            path: 'role.detail/:id?',
            meta: {
                parentPath: 'sw.users.permissions.index'
            }
        }
    },

    settingsItem: {
        group: 'system',
        to: 'sw.users.permissions.index',
        icon: 'default-avatar-single'
    }
});
