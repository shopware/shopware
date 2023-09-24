// @ts-nocheck

/**
 * @package admin
 */
import ViewAdapter from 'src/core/adapter/view.adapter';

// Vue3 imports
import { createI18n } from 'vue-i18n_v3';
import type { FallbackLocale, I18n } from 'vue-i18n_v3';
import type Router from 'vue-router_v3';

// Vue2 imports
import VueRouter from 'vue-router';
import VueI18n from 'vue-i18n';
import VueMeta from 'vue-meta';

import Vue, { createApp, defineAsyncComponent, h } from 'vue';
import type { AsyncComponent, Component as VueComponent, PluginObject } from 'vue';
import VuePlugins from 'src/app/plugin';
import setupShopwareDevtools from 'src/app/adapter/view/sw-vue-devtools';
import type ApplicationBootstrapper from 'src/core/application';
import type { ComponentConfig } from 'src/core/factory/async-component.factory';
import type { Store } from 'vuex';

const { Component, State, Mixin } = Shopware;

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
export default class VueAdapter extends ViewAdapter {
    private resolvedComponentConfigs: Map<string, ComponentConfig>;

    private vueComponents: {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        [componentName: string]: VueComponent<any, any, any, any> | AsyncComponent<any, any, any, any>
    };

    private i18n?: I18n;

    public app;

    private vue3 = false;

    constructor(Application: ApplicationBootstrapper) {
        super(Application);

        this.i18n = undefined;
        this.resolvedComponentConfigs = new Map();
        this.vueComponents = {};
        // @ts-expect-error
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        this.vue3 = !!window._features_?.vue3;

        if (this.vue3) {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
            this.app = createApp({ name: 'ShopwareAdministration', template: '<sw-admin />' }) as Vue;
        }
    }

    /**
     * Creates the main instance for the view layer.
     * Is used on startup process of the main application.
     */
    // @ts-expect-error
    init(renderElement: string, router: Router, providers: { [key: string]: unknown }): Vue {
        if (this.vue3) {
            return this.initVue3(renderElement, router, providers);
        }

        return this.initVue2(renderElement, router as VueRouter, providers);
    }

    // @ts-expect-error
    initVue3(renderElement: string, router: Router, providers: { [key: string]: unknown }): Vue {
        this.initPlugins();
        this.initDirectives();
        this.initFilters();
        this.initTitle();

        const store = State._store;
        const i18n = this.initLocales(store) as VueI18n;

        // add router to View
        this.router = router as VueRouter;
        // add i18n to View
        this.i18n = i18n as I18n;

        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        this.app.config.compilerOptions.whitespace = 'preserve';
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        this.app.config.performance = process.env.NODE_ENV !== 'production';
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access
        this.app.config.globalProperties.$t = i18n.global.t;
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access
        this.app.config.globalProperties.$tc = i18n.global.tc;

        /**
         * This is a hack for providing the services to the components.
         * We shouldn't use this anymore because it is not supported well
         * in Vue3 (because the services are lazy loaded).
         *
         * So we should convert from provide/inject to Shopware.Service
         */
        Object.keys(providers).forEach((provideKey) => {
            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            Object.defineProperty(this.app._context.provides, provideKey, {
                get: () => providers[provideKey],
                enumerable: true,
                configurable: true,
                // eslint-disable-next-line @typescript-eslint/no-empty-function
                set() {},
            });
        });

        this.root = this.app;

        // eslint-disable-next-line @typescript-eslint/no-unsafe-call
        this.app.use(router);
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call
        this.app.use(store);
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call
        this.app.use(i18n);

        // Add global properties to root view instance
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access
        this.app.$tc = i18n.global.tc;
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access
        this.app.$t = i18n.global.t;

        /* eslint-disable max-len */
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access
        this.app.config.globalProperties.$createTitle = function createTitle(this: Vue, identifier: string|null = null, ...additionalParams): string {
            if (!this.$root) {
                return '';
            }

            const baseTitle = this.$root.$tc('global.sw-admin-menu.textShopwareAdmin');

            if (!this.$route.meta || !this.$route.meta.$module) {
                return '';
            }

            // eslint-disable-next-line @typescript-eslint/no-unsafe-argument,@typescript-eslint/no-unsafe-member-access
            const pageTitle = this.$root.$tc(this.$route.meta.$module.title);

            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            const params = [baseTitle, pageTitle, identifier, ...additionalParams].filter((item) => {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
                return item !== null && item.trim() !== '';
            });

            return params.reverse().join(' | ');
        };
        /* eslint-enable max-len */

        // eslint-disable-next-line @typescript-eslint/no-unsafe-call
        this.app.mount(renderElement);

        if (process.env.NODE_ENV === 'development') {
            setupShopwareDevtools(this.root);
        }

        return this.root;
    }

    initVue2(renderElement: string, router: VueRouter, providers: { [key: string]: unknown }): Vue {
        this.initPlugins();
        this.initDirectives();
        this.initFilters();
        this.initTitle();

        const store = State._store;
        const i18n = this.initLocales(store);
        const components = this.getComponents();

        // add router to View
        this.router = router;
        // add i18n to View
        this.i18n = i18n;

        // Enable performance measurements in development mode
        Vue.config.performance = process.env.NODE_ENV !== 'production';

        this.root = new Vue({
            el: renderElement,
            template: '<sw-admin />',
            router,
            store,
            i18n,
            provide() {
                /**
                 * Vue 2.7 creates a new copy for each provided value. This caused problems with bottlejs.
                 * There should be only one instance of each provided value. Therefore we use a getter wrapper.
                 */
                return Object.keys(providers).reduce<{
                    [key: string]: unknown
                }>((acc, provideKey) => {
                    Object.defineProperty(acc, provideKey, {
                        get: () => providers[provideKey],
                        enumerable: true,
                        configurable: true,
                        // eslint-disable-next-line @typescript-eslint/no-empty-function
                        set() {},
                    });

                    return acc;
                }, {});
            },
            components,
            data() {
                return {
                    initError: {},
                };
            },
        });

        if (process.env.NODE_ENV === 'development') {
            setupShopwareDevtools(this.root);
        }

        return this.root;
    }

    /**
     * Initialize of all dependencies.
     */
    async initDependencies() {
        const initContainer = this.Application.getContainer('init');

        // make specific components synchronous
        [
            'sw-admin',
            'sw-admin-menu',
            'sw-button',
            'sw-button-process',
            'sw-card',
            'sw-card-section',
            'sw-card-view',
            'sw-container',
            'sw-desktop',
            'sw-empty-state',
            'sw-entity-listing',
            'sw-entity-multi-select',
            'sw-entity-multi-id-select',
            'sw-entity-single-select',
            'sw-error-boundary',
            'sw-extension-component-section',
            'sw-field',
            'sw-ignore-class',
            'sw-loader',
            'sw-modal',
            'sw-multi-select',
            'sw-notification-center',
            'sw-notifications',
            'sw-page',
            'sw-router-link',
            'sw-search-bar',
            'sw-select-result',
            'sw-single-select',
            'sw-skeleton',
            'sw-skeleton-bar',
            'sw-tabs',
            'sw-tabs-item',
            'sw-version',
            /**
             * Quickfix for modules with refs and sync behavior.
             * They should be removed from the list in the future
             * when their async problems got fixed.
             */
            'sw-sales-channel-products-assignment-single-products',
            'sw-sales-channel-product-assignment-categories',
            'sw-sales-channel-products-assignment-dynamic-product-groups',
            'sw-upload-listener',
            'sw-media-list-selection-v2',
            'sw-media-list-selection-item-v2',
            'sw-settings-document-detail',
            'sw-settings-product-feature-sets-detail',
            'sw-system-config',
            'sw-settings-search-searchable-content',
        ].forEach(componentName => {
            Component.markComponentAsSync(componentName);
        });

        // initialize all components
        await this.initComponents();

        // initialize all module locales
        this.initModuleLocales();

        // initialize all module routes
        const allRoutes = this.applicationFactory.module.getModuleRoutes();
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
        initContainer.router.addModuleRoutes(allRoutes);

        // create routes for core and plugins
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
        initContainer.router.createRouterInstance();
    }


    /**
     * Initializes all core components as Vue components.
     */
    async initComponents() {
        const componentRegistry = this.componentFactory.getComponentRegistry();
        this.componentFactory.resolveComponentTemplates();

        const initializedComponents = [...componentRegistry.keys()].map((name) => {
            return this.createComponent(name);
        });

        await Promise.all(initializedComponents);

        return this.vueComponents;
    }

    /**
     * Initializes all core components as Vue components.
     */
    initModuleLocales() {
        // Extend default snippets with module specific snippets
        const moduleSnippets = this.applicationFactory.module.getModuleSnippets();

        Object.entries(moduleSnippets).forEach(([key, moduleSnippet]) => {
            this.applicationFactory.locale.extend(key, moduleSnippet);
        });

        return this.applicationFactory.locale;
    }

    /**
     * Returns the component as a Vue component.
     * Includes the full rendered template with all overrides.
     */
    createComponent(componentName: string): Promise<Vue> {
        return new Promise((resolve) => {
            // load sync components directly
            if (Component.isSyncComponent && Component.isSyncComponent(componentName)) {
                const resolvedComponent = this.componentResolver(componentName);

                if (resolvedComponent === undefined) {
                    return;
                }

                // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                void resolvedComponent.then((component) => {
                    let vueComponent;

                    if (this.vue3) {
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                        this.app.component(componentName, component);
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-assignment
                        vueComponent = this.app.component(componentName);
                    } else {
                        // @ts-expect-error - resolved config does not match completely a standard vue component
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                        vueComponent = Vue.component(componentName, component);
                    }


                    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                    this.vueComponents[componentName] = vueComponent;
                    resolve(vueComponent as unknown as Vue);
                });

                return;
            }

            // load async components
            let vueComponent;
            if (this.vue3) {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                this.app.component(componentName, defineAsyncComponent({
                    // the loader function
                    loader: () => this.componentResolver(componentName),
                    // Delay before showing the loading component. Default: 200ms.
                    delay: 0,
                    loadingComponent: {
                        name: 'async-loading-component',
                        inheritAttrs: false,
                        render() {
                            return h('div', {
                                style: { display: 'none' },
                            });
                        },
                    },
                }));

                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-call
                vueComponent = this.app.component(componentName);
            } else {
                vueComponent = Vue.component(componentName, () => this.componentResolver(componentName));
            }

            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            this.vueComponents[componentName] = vueComponent;

            resolve(vueComponent as unknown as Vue);
        });
    }

    componentResolver(componentName: string) {
        if (!this.resolvedComponentConfigs.has(componentName)) {
            this.resolvedComponentConfigs.set(componentName, new Promise((resolve) => {
                void Component.build(componentName).then((componentConfig) => {
                    this.resolveMixins(componentConfig);

                    resolve(componentConfig);
                });
            }));
        }

        return this.resolvedComponentConfigs.get(componentName);
    }

    /**
     * Builds and creates a Vue component using the provided component configuration.
     */
    buildAndCreateComponent(componentConfig: ComponentConfig) {
        if (!componentConfig.name) {
            throw new Error('Component name is missing');
        }

        const componentName = componentConfig.name;
        this.resolveMixins(componentConfig);

        // @ts-expect-error - resolved config does not match completely a standard vue component
        const vueComponent = Vue.component(componentConfig.name, componentConfig);
        this.vueComponents[componentName] = vueComponent;

        return vueComponent;
    }

    /**
     * Returns a final Vue component by its name.
     */
    getComponent(componentName: string) {
        if (!this.vueComponents[componentName]) {
            return null;
        }

        return this.vueComponents[componentName] as Vue;
    }

    /**
     * Returns a final Vue component by its name without defineAsyncComponent
     * which cannot be used in the router.
     */
    getComponentForRoute(componentName: string) {
        return () => this.componentResolver(componentName);
    }

    /**
     * Returns the complete set of available Vue components.
     */
    // @ts-expect-error - resolved config for each component does not match completely a standard vue component
    getComponents() {
        return this.vueComponents;
    }

    /**
     * Returns the adapter wrapper
     */
    getWrapper() {
        return Vue;
    }

    /**
     * Returns the name of the adapter
     */
    getName(): string {
        return 'Vue.js';
    }

    /**
     * Returns the Vue.set function
     */
    setReactive(target: Vue, propertyName: string, value: unknown) {
        return Vue.set(target, propertyName, value);
    }

    /**
     * Returns the Vue.delete function
     */
    deleteReactive(target: Vue, propertyName: string) {
        return Vue.delete(target, propertyName);
    }

    /**
     * Private methods
     */

    /**
     * Initialises all plugins for VueJS
     *
     * @private
     */
    initPlugins() {
        // placeholder variable because import is not filterable
        let plugins = VuePlugins;

        if (!this.vue3) {
            // Add the community plugins to the plugin list
            plugins.push(VueRouter, VueI18n, VueMeta);

            // Remove our meta info plugin because we use the vue-meta plugin with Vue 2
            plugins = plugins.filter((plugin) => {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                return !plugin?.isMetaInfoPluginInstalled;
            });
        }

        VuePlugins.forEach((plugin) => {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            if (plugin?.install?.installed) {
                return;
            }

            if (this.vue3) {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                this.app.use(plugin as PluginObject<unknown>);
            } else {
                Vue.use(plugin as PluginObject<unknown>);
            }
        });

        return true;
    }

    /**
     * Initializes all custom directives.
     *
     * @private
     */
    initDirectives() {
        const registry = this.Application.getContainer('factory').directive.getDirectiveRegistry();

        registry.forEach((directive, name) => {
            Vue.directive(name, directive);
        });

        return true;
    }

    /**
     * Initialises helpful filters for global use
     *
     * @private
     */
    initFilters() {
        const registry = this.Application.getContainer('factory').filter.getRegistry();

        registry.forEach((factoryMethod, name) => {
            Vue.filter(name, factoryMethod);
        });

        return true;
    }

    /**
     * Initialises the standard locales.
     */
    initLocales(store: Store<VuexRootState>) {
        const registry = this.localeFactory.getLocaleRegistry();
        const messages = {};
        const fallbackLocale = Shopware.Context.app.fallbackLocale as FallbackLocale;

        registry.forEach((localeMessages, key) => {
            store.commit('registerAdminLocale', key);
            // @ts-expect-error - key is safe because we iterate through the registry
            messages[key] = localeMessages;
        });

        const lastKnownLocale = this.localeFactory.getLastKnownLocale();
        void store.dispatch('setAdminLocale', lastKnownLocale);

        const options = {
            locale: lastKnownLocale,
            fallbackLocale,
            silentFallbackWarn: true,
            sync: true,
            messages,
        };

        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        const i18n = window._features_?.vue3 ? createI18n(options) : new VueI18n(options);

        store.subscribe(({ type }, state) => {
            if (type === 'setAdminLocale') {
                // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
                i18n.locale = state.session.currentLocale!;
            }
        });

        this.setLocaleFromUser(store);

        return i18n;
    }

    setLocaleFromUser(store: Store<VuexRootState>) {
        const currentUser = store.state.session.currentUser;

        if (currentUser) {
            const userLocaleId = currentUser.localeId;
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            Shopware.Service('localeHelper').setLocaleWithId(userLocaleId);
        }
    }

    /**
     * Extends Vue prototype to access $createTitle function
     *
     * @private
     */
    initTitle() {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
        if (Vue.prototype.hasOwnProperty('$createTitle')) {
            return;
        }

        /**
         * Generates the document title out of the given VueComponent and parameters
         */
        // @ts-expect-error - additionalParams is not typed
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,max-len
        Vue.prototype.$createTitle = function createTitle(this: Vue, identifier: string|null = null, ...additionalParams): string {
            if (!this.$root) {
                return '';
            }

            const baseTitle = this.$root.$tc('global.sw-admin-menu.textShopwareAdmin');

            if (!this.$route.meta || !this.$route.meta.$module) {
                return '';
            }

            // eslint-disable-next-line @typescript-eslint/no-unsafe-argument,@typescript-eslint/no-unsafe-member-access
            const pageTitle = this.$root.$tc(this.$route.meta.$module.title);

            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            const params = [baseTitle, pageTitle, identifier, ...additionalParams].filter((item) => {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
                return item !== null && item.trim() !== '';
            });

            return params.reverse().join(' | ');
        };
    }

    /**
     * Recursively resolves mixins referenced by name
     *
     * @private
     */
    resolveMixins(componentConfig: ComponentConfig) {
        // If the mixin is a string, use our mixin registry
        if (componentConfig.mixins?.length) {
            componentConfig.mixins = componentConfig.mixins.map((mixin) => {
                if (typeof mixin === 'string') {
                    return Mixin.getByName(mixin);
                }

                return mixin;
            });
        }

        if (componentConfig.extends) {
            // @ts-expect-error - extends can be a string or a component config
            this.resolveMixins(componentConfig.extends);
        }
    }
}
