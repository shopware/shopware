/**
 * @sw-package framework
 */
import './acl';

if (Shopware.Feature.isActive('ADMIN_AUTH')) {
    /* eslint-disable sw-deprecation-rules/private-feature-declarations */
    Shopware.Component.register('sw-settings-admin-auth-index', () => import('./page/sw-settings-admin-auth-index'));
    Shopware.Component.register(
        'sw-settings-admin-auth-provider-list',
        () => import('./page/sw-settings-admin-auth-provider-list'),
    );
    Shopware.Component.register(
        'sw-settings-admin-auth-provider-detail',
        () => import('./page/sw-settings-admin-auth-provider-detail'),
    );
    Shopware.Component.register(
        'sw-settings-admin-auth-role-mapping',
        () => import('./component/sw-settings-admin-auth-role-mapping'),
    );
    /* eslint-enable sw-deprecation-rules/private-feature-declarations */

    // When the YAML configuration disables provider management entirely, the module stays
    // reachable for deep links but is not offered in the settings overview.
    const adminUiDisabled = Shopware.Context.app.config.settings?.adminAuth?.adminUiDisabled === true;

    // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
    Shopware.Module.register('sw-settings-admin-auth', {
        type: 'core',
        name: 'settings-admin-auth',
        title: 'sw-settings-admin-auth.general.mainMenuItemGeneral',
        description: 'sw-settings-admin-auth.general.description',
        color: '#9AA8B5',
        icon: 'regular-lock',
        favicon: 'icon-module-settings.png',

        routes: {
            index: {
                component: 'sw-settings-admin-auth-index',
                path: 'index',
                meta: {
                    parentPath: 'sw.settings.index.system',
                    privilege: 'admin_auth.viewer',
                },
            },
            providers: {
                component: 'sw-settings-admin-auth-provider-list',
                path: 'providers',
                meta: {
                    parentPath: 'sw.settings.index.system',
                    privilege: 'admin_auth.viewer',
                },
            },
            detail: {
                component: 'sw-settings-admin-auth-provider-detail',
                path: 'provider/detail/:id',
                meta: {
                    parentPath: 'sw.settings.admin.auth.providers',
                    privilege: 'admin_auth.viewer',
                },
                props: {
                    default(route: { params: { id: string } }) {
                        return {
                            providerId: route.params.id.toLowerCase(),
                        };
                    },
                },
            },
            create: {
                component: 'sw-settings-admin-auth-provider-detail',
                path: 'provider/create',
                meta: {
                    parentPath: 'sw.settings.admin.auth.providers',
                    privilege: 'admin_auth.creator',
                },
            },
        },

        ...(adminUiDisabled
            ? {}
            : {
                  settingsItem: {
                      group: 'system',
                      to: 'sw.settings.admin.auth.index',
                      icon: 'regular-lock',
                      privilege: 'admin_auth.viewer',
                  },
              }),
    });
}

/**
 * @private
 * @sw-package framework
 */
export {};
