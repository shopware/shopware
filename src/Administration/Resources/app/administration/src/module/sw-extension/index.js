import initState from './store';
import './mixin/sw-extension-error.mixin';
import './service';
import './acl';
import './component/sw-extension-adding-failed/register';
import './component/sw-extension-adding-success/register';
import './component/sw-extension-app-module-error-page/register';
import './component/sw-extension-bulk-actions-bar/register';
import './component/sw-extension-bulk-deactivation-modal/register';
import './component/sw-extension-bulk-uninstall-modal/register';
import './component/sw-extension-card-base/register';
import './component/sw-extension-card-bought/register';
import './component/sw-extension-deactivation-modal/register';
import './component/sw-extension-domains-modal/register';
import './component/sw-extension-file-upload/register';
import './component/sw-extension-my-extensions-listing-controls/register';
import './component/sw-extension-permissions-details-modal/register';
import './component/sw-extension-permissions-modal/register';
import './component/sw-extension-privacy-policy-extensions-modal/register';
import './component/sw-extension-removal-modal/register';
import './component/sw-extension-uninstall-modal/register';
import './component/sw-ratings/sw-extension-review-creation/register';
import './component/sw-ratings/sw-extension-rating-modal/register';
import './component/sw-ratings/sw-extension-rating-stars/register';
import './component/sw-ratings/sw-extension-ratings-card/register';
import './component/sw-ratings/sw-extension-ratings-summary/register';
import './component/sw-ratings/sw-extension-review/register';
import './component/sw-ratings/sw-extension-review-creation-inputs/register';
import './component/sw-ratings/sw-extension-review-reply/register';
import './component/sw-ratings/sw-extension-select-rating/register';
import './page/sw-extension-app-module-page/register';
import './page/sw-extension-config/register';
import './page/sw-extension-my-extensions-account/register';
import './page/sw-extension-my-extensions-index/register';
import './page/sw-extension-my-extensions-listing/register';
import './page/sw-extension-my-extensions-recommendation/register';
import './page/sw-extension-store-landing-page/register';

initState(Shopware);

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopware.Component.extend(
    'sw-self-maintained-extension-card',
    'sw-extension-card-base',
    () => import('./component/sw-self-maintained-extension-card'),
);
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

/**
 * @sw-package checkout
 * @private
 */
Shopware.Module.register('sw-extension', {
    type: 'core',
    title: 'sw-extension-store.title',
    description: 'sw-extension-store.descriptionTextModule',
    color: 'var(--sw-color-module-brand-default)',
    icon: 'regular-plug',
    version: '1.0.0',
    targetVersion: '1.0.0',
    entity: 'extension',

    searchMatcher: (regex, labelType, manifest) => {
        const match = labelType.toLowerCase().match(regex);

        if (!match) {
            return false;
        }

        return [
            {
                icon: manifest.icon,
                color: manifest.color,
                label: labelType,
                entity: manifest.entity,
                route: manifest.routes.store,
                privilege: manifest.routes.index?.meta.privilege,
            },
        ];
    },

    routes: {
        'my-extensions': {
            path: 'my-extensions',
            component: 'sw-extension-my-extensions-index',
            redirect: {
                name: 'sw.extension.my-extensions.listing',
            },
            meta: {
                privilege: 'system.plugin_maintain',
            },
            children: {
                listing: {
                    path: 'listing',
                    component: 'sw-extension-my-extensions-listing',
                    redirect: {
                        name: 'sw.extension.my-extensions.listing.app',
                    },
                    meta: {
                        privilege: 'system.plugin_maintain',
                    },
                    children: {
                        app: {
                            path: 'app',
                            component: 'sw-extension-my-extensions-listing',
                            propsData: {
                                isTheme: false,
                            },
                            meta: {
                                privilege: 'system.plugin_maintain',
                            },
                        },
                        theme: {
                            path: 'theme',
                            component: 'sw-extension-my-extensions-listing',
                            propsData: {
                                isTheme: true,
                            },
                            meta: {
                                privilege: 'system.plugin_maintain',
                            },
                        },
                    },
                },
                recommendation: {
                    path: 'recommendation',
                    component: 'sw-extension-my-extensions-recommendation',
                    meta: {
                        privilege: 'system.plugin_maintain',
                    },
                },
                account: {
                    path: 'account',
                    component: 'sw-extension-my-extensions-account',
                    meta: {
                        privilege: 'system.plugin_maintain',
                    },
                },
            },
        },
        config: {
            component: 'sw-extension-config',
            path: 'config/:namespace',
            meta: {
                parentPath: 'sw.extension.my-extensions',
                privilege: 'system.plugin_maintain',
            },

            props: {
                default(route) {
                    return { namespace: route.params.namespace };
                },
            },
        },

        store: {
            path: 'store',
            component: 'sw-extension-store-landing-page',
            redirect: {
                name: 'sw.extension.store.landing-page',
            },
        },

        'store.landing-page': {
            path: 'store/landing-page',
            component: 'sw-extension-store-landing-page',
            meta: {
                parentPath: 'sw.extension.store',
            },
        },

        module: {
            path: 'module/:appName/:moduleName?',
            component: 'sw-extension-app-module-page',
            props: {
                default(route) {
                    const { appName, moduleName } = route.params;
                    return {
                        appName,
                        moduleName,
                    };
                },
            },
        },
    },

    navigation: [
        {
            id: 'sw-extension',
            label: 'sw-extension.mainMenu.mainMenuItemExtensionStore',
            color: 'var(--sw-color-module-brand-default)',
            icon: 'regular-plug',
            position: 80,
        },
        {
            id: 'sw-extension-store',
            parent: 'sw-extension',
            label: 'sw-extension.mainMenu.store',
            path: 'sw.extension.store',
            privilege: 'system.extension_store',
            position: 10,
        },
        {
            id: 'sw-extension-my-extensions',
            parent: 'sw-extension',
            label: 'sw-extension.mainMenu.purchased',
            path: 'sw.extension.my-extensions',
            privilege: 'system.plugin_maintain',
            position: 10,
        },
    ],
});
