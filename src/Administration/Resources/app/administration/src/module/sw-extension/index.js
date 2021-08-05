import initState from './store';
import extensionErrorMixin from './mixin/sw-extension-error.mixin';

async function initDependencies() {
    await import(/* webpackMode: 'eager' */ './service');
    await import(/* webpackMode: 'eager' */ './page/sw-extension-config');
    await import(/* webpackMode: 'eager' */ './page/sw-extension-my-extensions-listing');
    await import(/* webpackMode: 'eager' */ './page/sw-extension-my-extensions-index');
    await import(/* webpackMode: 'eager' */ './page/sw-extension-my-extensions-account');
    await import(/* webpackMode: 'eager' */ './page/sw-extension-store-landing-page');
    await import(/* webpackMode: 'eager' */ './page/sw-extension-my-extensions-recommendation');
    await import(/* webpackMode: 'eager' */ './component/sw-extension-file-upload');
    await import(/* webpackMode: 'eager' */ './component/sw-extension-permissions-details-modal');
    await import(/* webpackMode: 'eager' */ './component/sw-extension-card-base');
    await import(/* webpackMode: 'eager' */ './component/sw-extension-card-bought');
    await import(/* webpackMode: 'eager' */ './component/sw-self-maintained-extension-card');
    await import(/* webpackMode: 'eager' */ './component/sw-extension-my-extensions-listing-controls');
    await import(/* webpackMode: 'eager' */ './component/sw-extension-permissions-modal');
    await import(/* webpackMode: 'eager' */ './component/sw-extension-domains-modal');
    await import(/* webpackMode: 'eager' */ './component/sw-extension-privacy-policy-extensions-modal');
    await import(/* webpackMode: 'eager' */ './component/sw-extension-deactivation-modal');
    await import(/* webpackMode: 'eager' */ './component/sw-extension-removal-modal');
    await import(/* webpackMode: 'eager' */ './component/sw-extension-uninstall-modal');
    await import(/* webpackMode: 'eager' */ './component/sw-ratings/sw-extension-rating-stars');
    await import(/* webpackMode: 'eager' */ './component/sw-ratings/sw-extension-ratings-card');
    await import(/* webpackMode: 'eager' */ './component/sw-ratings/sw-extension-ratings-summary');
    await import(/* webpackMode: 'eager' */ './component/sw-ratings/sw-extension-review');
    await import(/* webpackMode: 'eager' */ './component/sw-ratings/sw-extension-review-creation');
    await import(/* webpackMode: 'eager' */ './component/sw-ratings/sw-extension-review-creation-inputs');
    await import(/* webpackMode: 'eager' */ './component/sw-ratings/sw-extension-review-reply');
    await import(/* webpackMode: 'eager' */ './component/sw-ratings/sw-extension-select-rating');
    await import(/* webpackMode: 'eager' */ './component/sw-ratings/sw-extension-rating-modal');
    await import(/* webpackMode: 'eager' */ './component/sw-extension-adding-failed');
    await import(/* webpackMode: 'eager' */ './component/sw-extension-adding-success');
    await import(/* webpackMode: 'eager' */ './acl');
}

let errorMixin = extensionErrorMixin;
if (!Shopware.Feature.isActive('FEATURE_NEXT_12608')) {
    errorMixin = {
        mixins: [Shopware.Mixin.getByName('notification')],

        methods: {
            showExtensionErrors(errorResponse) {
                console.error(errorResponse);
            },
        },
    };
}
Shopware.Mixin.register('sw-extension-error', errorMixin);


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
