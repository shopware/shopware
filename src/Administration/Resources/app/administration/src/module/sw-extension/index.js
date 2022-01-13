import initState from './store';
import './mixin/sw-extension-error.mixin';
import './service';
import './page/sw-extension-config';
import './page/sw-extension-my-extensions-listing';
import './page/sw-extension-my-extensions-index';
import './page/sw-extension-my-extensions-account';
import './page/sw-extension-store-landing-page';
import './page/sw-extension-my-extensions-recommendation';
import './component/sw-extension-file-upload';
import './component/sw-extension-permissions-details-modal';
import './component/sw-extension-card-base';
import './component/sw-extension-card-bought';
import './component/sw-self-maintained-extension-card';
import './component/sw-extension-my-extensions-listing-controls';
import './component/sw-extension-permissions-modal';
import './component/sw-extension-domains-modal';
import './component/sw-extension-privacy-policy-extensions-modal';
import './component/sw-extension-deactivation-modal';
import './component/sw-extension-removal-modal';
import './component/sw-extension-uninstall-modal';
import './component/sw-ratings/sw-extension-rating-stars';
import './component/sw-ratings/sw-extension-ratings-card';
import './component/sw-ratings/sw-extension-ratings-summary';
import './component/sw-ratings/sw-extension-review';
import './component/sw-ratings/sw-extension-review-creation';
import './component/sw-ratings/sw-extension-review-creation-inputs';
import './component/sw-ratings/sw-extension-review-reply';
import './component/sw-ratings/sw-extension-select-rating';
import './component/sw-ratings/sw-extension-rating-modal';
import './component/sw-extension-adding-failed';
import './component/sw-extension-adding-success';
import './acl';

initState(Shopware);

Shopware.Module.register('sw-extension', {
    type: 'core',
    title: 'sw-extension-store.title',
    description: 'sw-extension-store.descriptionTextModule',
    color: '#189EFF',
    icon: 'default-object-plug',
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
        },
    },

    navigation: [
        {
            id: 'sw-extension',
            label: 'sw-extension.mainMenu.mainMenuItemExtensionStore',
            color: '#189EFF',
            icon: 'default-object-plug',
            position: 70,
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
