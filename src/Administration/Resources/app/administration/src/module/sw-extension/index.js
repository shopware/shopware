import './service';
import initState from './store';
import './mixin';
import './component/sw-extension-meteor-page';
import './component/sw-extension-meteor-navigation';
import './component/sw-extension-store-listing-filter';
import './component/sw-extension-listing-card';
import './component/sw-extension-store-purchased/sw-extension-card-base';
import './component/sw-extension-store-purchased/sw-extension-deactivation-modal';
import './component/sw-extension-store-purchased/sw-bought-extension-card';
import './component/sw-extension-store-purchased/sw-self-maintained-extension-card';
import './component/sw-extension-store-purchased/sw-extension-removal-modal';
import './component/sw-extension-store-purchased/sw-extension-uninstall-modal';
import './page/sw-extension-config';
import './page/sw-extension-store-index';
import './page/sw-extension-store-listing';
import './page/sw-extension-my-extensions-listing';
import './page/sw-extension-my-extensions-index';
import './page/sw-extension-my-extensions-account';
import './page/sw-extension-store-detail';
import './component/sw-ratings/sw-extension-rating-stars';
import './component/sw-ratings/sw-ratings-card';
import './component/sw-ratings/sw-ratings-summary';
import './component/sw-ratings/sw-extension-review';
import './component/sw-ratings/sw-review-creation';
import './component/sw-ratings/sw-review-creation-inputs';
import './component/sw-ratings/sw-review-reply';
import './component/sw-ratings/sw-select-rating';
import './component/sw-extension-file-upload';
import './component/sw-extension-buy-modal';
import './component/sw-extension-permissions-modal';
import './component/sw-extension-permissions-details-modal';
import './component/sw-extension-privacy-policy-extensions-modal';
import './component/sw-extensions-store-slider';
import './component/sw-ratings/sw-extension-rating-modal';
import './component/context-button-bar/sw-extension-meteor-page-context';
import './component/context-button-bar/sw-extension-meteor-page-context-item';
import './component/context-button-bar/sw-extension-meteor-page-context-menu-item';
import './component/context-button-bar/sw-extension-page-context-menu';
import './component/sw-extension-gtc-checkbox';

if (Shopware.Feature.isActive('FEATURE_NEXT_12608')) {
    initState(Shopware);
}

Shopware.Module.register('sw-extension', {
    type: 'core',
    title: 'sw-extension-store.title',
    description: 'sw-extension-store.descriptionTextModule',
    color: '#189EFF',
    icon: 'default-object-plug',
    version: '1.0.0',
    targetVersion: '1.0.0',
    flag: 'FEATURE_NEXT_12608',

    routes: {
        store: {
            path: 'store',
            redirect: {
                name: 'sw.extension.store.listing'
            },
            component: 'sw-extension-store-index',
            children: {
                listing: {
                    path: 'listing',
                    component: 'sw-extension-store-listing',
                    redirect: {
                        name: 'sw.extension.store.listing.app'
                    },
                    children: {
                        app: {
                            path: 'app',
                            component: 'sw-extension-store-listing',
                            propsData: {
                                isTheme: false
                            }
                        },
                        theme: {
                            path: 'theme',
                            component: 'sw-extension-store-listing',
                            propsData: {
                                isTheme: true
                            }
                        }
                    }
                }
            }
        },
        'store.detail': {
            component: 'sw-extension-store-detail',
            path: 'store/detail/:id',
            meta: {
                parentPath: 'sw.extension.store'
            },
            props: {
                default: (route) => {
                    return { id: route.params.id };
                }
            }
        },
        'my-extensions': {
            path: 'my-extensions',
            component: 'sw-extension-my-extensions-index',
            redirect: {
                name: 'sw.extension.my-extensions.listing'
            },
            children: {
                listing: {
                    path: 'listing',
                    component: 'sw-extension-my-extensions-listing',
                    redirect: {
                        name: 'sw.extension.my-extensions.listing.app'
                    },
                    children: {
                        app: {
                            path: 'app',
                            component: 'sw-extension-my-extensions-listing',
                            propsData: {
                                isTheme: false
                            }
                        },
                        theme: {
                            path: 'theme',
                            component: 'sw-extension-my-extensions-listing',
                            propsData: {
                                isTheme: true
                            }
                        }
                    }
                },
                account: {
                    path: 'account',
                    component: 'sw-extension-my-extensions-account'
                }
            }
        },
        config: {
            component: 'sw-extension-config',
            path: 'config/:namespace',
            meta: {
                parentPath: 'sw.extension.my-extensions'
            },

            props: {
                default(route) {
                    return { namespace: route.params.namespace };
                }
            }
        }
    },

    navigation: [{
        id: 'sw-extension',
        label: 'sw-extension.mainMenu.mainMenuItemExtensionStore',
        color: '#189EFF',
        icon: 'default-object-plug',
        position: 70
    },
    {
        id: 'sw-extension-store',
        parent: 'sw-extension',
        label: 'sw-extension.mainMenu.store',
        path: 'sw.extension.store.listing',
        position: 10
    },
    {
        id: 'sw-extension-my-extensions',
        parent: 'sw-extension',
        label: 'sw-extension.mainMenu.purchased',
        path: 'sw.extension.my-extensions',
        position: 10
    }
    ]
});
