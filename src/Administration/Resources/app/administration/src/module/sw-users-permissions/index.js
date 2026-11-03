/**
 * @sw-package fundamentals@framework
 */
import './acl';
import './component/sw-users-permissions-role-mcp-reference-modal/register';
import './components/sw-user-sso-access-key-create-modal/register';
import './components/sw-user-sso-invitation-modal/register';
import './components/sw-user-sso-status-label/register';
import './components/sw-users-permissions-additional-permissions/register';
import './components/sw-users-permissions-configuration/register';
import './components/sw-users-permissions-detailed-additional-permissions/register';
import './components/sw-users-permissions-permissions-grid/register';
import './components/sw-users-permissions-role-listing/register';
import './components/sw-users-permissions-user-listing/register';
import './page/sw-sso-users-permission-user-detail/register';
import './page/sw-users-permissions/register';
import './page/sw-users-permissions-role-detail/register';
import './page/sw-users-permissions-user-detail/register';
import './page/sw-users-permissions-user-create/register';
import './view/sw-users-permissions-role-view-detailed/register';
import './view/sw-users-permissions-role-view-general/register';
import './components/sw-users-permissions-detailed-permissions-grid/register';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Module.register('sw-users-permissions', {
    type: 'core',
    name: 'users-permissions',
    title: 'sw-users-permissions.general.label',
    description: 'sw-users-permissions.general.label',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-user',
    favicon: 'icon-module-settings.svg',
    entity: 'user',

    routes: {
        index: {
            component: 'sw-users-permissions',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index.system',
                privilege: 'users_and_permissions.viewer',
            },
        },
        'user.detail': {
            component: 'sw-users-permissions-user-detail',
            path: 'user.detail/:id?',
            meta: {
                parentPath: 'sw.users.permissions.index',
                privilege: 'users_and_permissions.viewer',
            },
        },
        'user.sso.detail': {
            component: 'sw-sso-users-permission-user-detail',
            path: 'user.sso.detail/:id?',
            meta: {
                parentPath: 'sw.users.permissions.index',
                privilege: 'users_and_permissions.viewer',
            },
        },
        'user.create': {
            component: 'sw-users-permissions-user-create',
            path: 'user.create',
            meta: {
                parentPath: 'sw.users.permissions.index',
                privilege: 'users_and_permissions.creator',
            },
        },
        'role.detail': {
            component: 'sw-users-permissions-role-detail',
            path: 'role.detail/:id?',
            meta: {
                parentPath: 'sw.users.permissions.index',
                privilege: 'users_and_permissions.viewer',
            },
            redirect: {
                name: 'sw.users.permissions.role.detail.general',
            },
            children: {
                general: {
                    component: 'sw-users-permissions-role-view-general',
                    path: 'general',
                    meta: {
                        parentPath: 'sw.users.permissions.index',
                        privilege: 'users_and_permissions.viewer',
                    },
                },
                'detailed-privileges': {
                    component: 'sw-users-permissions-role-view-detailed',
                    path: 'detailed-privileges',
                    meta: {
                        parentPath: 'sw.users.permissions.index',
                        privilege: 'users_and_permissions.viewer',
                    },
                },
            },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'sw.users.permissions.index',
        icon: 'regular-user',
        privilege: 'users_and_permissions.viewer',
    },
});
