import initState from './store';

async function initDependencies() {
    await import(/* webpackMode: 'eager' */ './service');
    await import(/* webpackMode: 'eager' */ './component/sw-extension-store-listing-filter');
    await import(/* webpackMode: 'eager' */ './component/sw-extension-listing-card');
    await import(/* webpackMode: 'eager' */ './component/sw-extension-store-purchased/sw-extension-card-base');
    await import(/* webpackMode: 'eager' */ './component/sw-extension-store-purchased/sw-extension-deactivation-modal');
    await import(/* webpackMode: 'eager' */ './component/sw-extension-store-purchased/sw-bought-extension-card');
    await import(/* webpackMode: 'eager' */ './component/sw-extension-store-purchased/sw-self-maintained-extension-card');
    await import(/* webpackMode: 'eager' */ './component/sw-extension-store-purchased/sw-extension-removal-modal');
    await import(/* webpackMode: 'eager' */ './component/sw-extension-store-purchased/sw-extension-uninstall-modal');
    await import(/* webpackMode: 'eager' */ './page/sw-extension-config');
    await import(/* webpackMode: 'eager' */ './page/sw-extension-store-index');
    await import(/* webpackMode: 'eager' */ './page/sw-extension-store-listing');
    await import(/* webpackMode: 'eager' */ './page/sw-extension-my-extensions-listing');
    await import(/* webpackMode: 'eager' */ './page/sw-extension-my-extensions-index');
    await import(/* webpackMode: 'eager' */ './page/sw-extension-my-extensions-account');
    await import(/* webpackMode: 'eager' */ './page/sw-extension-store-detail');
    await import(/* webpackMode: 'eager' */ './component/sw-ratings/sw-extension-rating-stars');
    await import(/* webpackMode: 'eager' */ './component/sw-ratings/sw-ratings-card');
    await import(/* webpackMode: 'eager' */ './component/sw-ratings/sw-ratings-summary');
    await import(/* webpackMode: 'eager' */ './component/sw-ratings/sw-extension-review');
    await import(/* webpackMode: 'eager' */ './component/sw-ratings/sw-review-creation');
    await import(/* webpackMode: 'eager' */ './component/sw-ratings/sw-review-creation-inputs');
    await import(/* webpackMode: 'eager' */ './component/sw-ratings/sw-review-reply');
    await import(/* webpackMode: 'eager' */ './component/sw-ratings/sw-select-rating');
    await import(/* webpackMode: 'eager' */ './component/sw-extension-file-upload');
    await import(/* webpackMode: 'eager' */ './component/sw-extension-buy-modal');
    await import(/* webpackMode: 'eager' */ './component/sw-extension-permissions-modal');
    await import(/* webpackMode: 'eager' */ './component/sw-extension-permissions-details-modal');
    await import(/* webpackMode: 'eager' */ './component/sw-extension-privacy-policy-extensions-modal');
    await import(/* webpackMode: 'eager' */ './component/sw-extensions-store-slider');
    await import(/* webpackMode: 'eager' */ './component/sw-ratings/sw-extension-rating-modal');
    await import(/* webpackMode: 'eager' */ './component/sw-extension-gtc-checkbox');
}

if (Shopware.Feature.isActive('FEATURE_NEXT_12608')) {
    initState(Shopware);
    initDependencies();
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
