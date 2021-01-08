import './service';
import './store';
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
import './page/sw-extension-store-index/views/sw-extension-store-listing';
import './page/sw-extension-store-purchased';
import './page/sw-extension-store-support';
import './page/sw-extension-store-detail';
import './component/sw-ratings/sw-extension-rating-stars';
import './component/sw-ratings/sw-ratings-card';
import './component/sw-ratings/sw-ratings-summary';
import './component/sw-ratings/sw-extension-review';
import './component/sw-ratings/sw-review-creation';
import './component/sw-ratings/sw-review-creation-inputs';
import './component/sw-ratings/sw-review-reply';
import './component/sw-ratings/sw-select-rating';
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
import './component/sw-meteor-card';
import './component/sw-extension-gtc-checkbox';

Shopware.Module.register('sw-extension-store', {
    type: 'core',
    title: 'sw-extension-store.title',
    description: 'sw-extension-store.descriptionTextModule',
    routePrefixPath: 'extensions',
    color: '#189EFF',
    icon: 'default-object-plug',
    version: '1.0.0',
    targetVersion: '1.0.0',
    flag: 'FEATURE_NEXT_12608',

    routes: {
        index: {
            path: 'store',
            redirect: {
                name: 'sw.extension.store.index.extensions'
            },
            component: 'sw-extension-store-index',
            children: {
                extensions: {
                    path: 'extensions',
                    component: 'sw-extension-store-listing'
                }
            }
        },
        detail: {
            component: 'sw-extension-store-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.extension.store.index'
            },
            props: {
                default: (route) => {
                    return { extensionId: route.params.id };
                }
            }
        },
        purchased: {
            path: 'purchased',
            component: 'sw-extension-store-purchased',
            redirect: {
                name: 'sw.extension.store.purchased.app'
            },
            children: {
                app: {
                    path: 'app',
                    component: 'sw-extension-store-purchased',
                    propsData: {
                        isTheme: false
                    }
                },
                theme: {
                    path: 'theme',
                    component: 'sw-extension-store-purchased',
                    propsData: {
                        isTheme: true
                    }
                }
            }
        },
        settings: {
            component: 'sw-extension-config',
            path: 'settings/:namespace',
            meta: {
                parentPath: 'sw.extension.store.purchased'
            },

            props: {
                default(route) {
                    return { namespace: route.params.namespace };
                }
            }
        }
        // TODO: implement in SAAS-1138

        // support: {
        //     path: 'support',
        //     component: 'sw-extension-store-support',
        // },
    },

    navigation: [{
        id: 'sw-extension-store',
        label: 'sw-extension-store.mainMenuItemExtensionStore',
        color: '#189EFF',
        icon: 'default-object-plug',
        position: 70
    }, {
        id: 'sw-extension-store-store',
        parent: 'sw-extension-store',
        label: 'sw-extension-store.mainMenu.store',
        path: 'sw.extension.store.index',
        position: 10
    }, {
        id: 'sw-extension-store-purchased',
        parent: 'sw-extension-store',
        label: 'sw-extension-store.mainMenu.purchased',
        path: 'sw.extension.store.purchased',
        position: 10
    }

    // {
    //     id: 'sw-extension-store-support',
    //     parent: 'sw-extension-store',
    //     label: 'sw-extension-store.mainMenu.support',
    //     path: 'sw.extension.store.support',
    //     position: 10,
    // }
    ]
});
