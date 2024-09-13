/**
 * @package admin
 */
import ViewAdapter from 'src/core/adapter/view.adapter';
import { createI18n } from 'vue-i18n';
import type { FallbackLocale, I18n } from 'vue-i18n';
import type { Router } from 'vue-router';
import type { Store as VuexStore } from 'vuex';
import { createApp, defineAsyncComponent, h } from 'vue';
import type { Component as VueComponent, App } from 'vue';
import VuePlugins from 'src/app/plugin';
import setupShopwareDevtools from 'src/app/adapter/view/sw-vue-devtools';
import type ApplicationBootstrapper from 'src/core/application';
import type { ComponentConfig } from 'src/core/factory/async-component.factory';
import type { ComponentPublicInstance } from '@vue/runtime-core';
// @ts-expect-error - compatUtils is not typed
import { compatUtils } from '@vue/compat';

import * as MeteorImport from '@shopware-ag/meteor-component-library';

const { Component, State, Store, Mixin } = Shopware;

/**
 * @private
 */
export default class VueAdapter extends ViewAdapter {
    private resolvedComponentConfigs: Map<string, Promise<ComponentConfig | boolean>>;

    private vueComponents: {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        [componentName: string]: VueComponent<any, any, any, any>
    };

    private i18n?: I18n;

    public app: App<Element>;

    constructor(Application: ApplicationBootstrapper) {
        super(Application);

        this.i18n = undefined;
        this.resolvedComponentConfigs = new Map();
        this.vueComponents = {};
        this.app = createApp({ name: 'ShopwareAdministration', template: '<sw-admin />' });
    }

    /**
     * Creates the main instance for the view layer.
     * Is used on startup process of the main application.
     */
    init(renderElement: string, router: Router, providers: { [key: string]: unknown }): App<Element> {
        return this.initVue(renderElement, router, providers);
    }

    initVue(renderElement: string, router: Router, providers: { [key: string]: unknown }): App<Element> {
        this.initPlugins();
        this.initDirectives();

        const vuexRoot = State._store;
        const piniaRoot = Store._rootState;
        // eslint-disable-next-line @typescript-eslint/ban-types
        const i18n = this.initLocales(vuexRoot) as I18n<{}, {}, {}, string, true>;

        // add router to View
        this.router = router;
        // add i18n to View
        this.i18n = i18n;

        if (!this.app) {
            throw new Error('Vue app is not initialized yet');
        }

        this.app.config.compilerOptions.whitespace = 'preserve';
        this.app.config.performance = process.env.NODE_ENV !== 'production';
        this.app.config.globalProperties.$t = i18n.global.t;
        this.app.config.globalProperties.$tc = i18n.global.tc;
        this.app.config.warnHandler = (
            msg: string,
            instance: unknown,
            trace: string,
        ) => {
            const warnArgs = [`[Vue warn]: ${msg}`, trace, instance];

            console.warn(...warnArgs);

            if (msg.includes('Template compilation error')) {
                console.error(...[`[Vue error]: ${msg}`, trace, instance]);
                throw new Error(msg);
            }
        };

        /**
         * This is a hack for providing the services to the components.
         * We shouldn't use this anymore because it is not supported well
         * in Vue3 (because the services are lazy loaded).
         *
         * So we should convert from provide/inject to Shopware.Service
         */
        Object.keys(providers).forEach((provideKey) => {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            Object.defineProperty(this.app._context.provides, provideKey, {
                get: () => providers[provideKey],
                enumerable: true,
                configurable: true,
                // eslint-disable-next-line @typescript-eslint/no-empty-function
                set() { },
            });
        });

        this.root = this.app;

        this.app.use(router);
        this.app.use(vuexRoot);
        this.app.use(i18n);

        // Custom compatUtils check on component basis
        this.app.use({
            install: (app) => {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                app.config.globalProperties.isCompatEnabled = function (key: string) {
                    // eslint-disable-next-line max-len
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-return,@typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-call
                    return this.$options.compatConfig?.[key] ?? compatUtils.isCompatEnabled(key);
                };
            },
        });


        // Add global properties to root view instance
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access
        this.app.$tc = i18n.global.tc;
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access
        this.app.$t = i18n.global.t;

        this.initTitle(this.app);
        /* eslint-enable max-len */

        this.app.use(piniaRoot);
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call
        this.app.mount(renderElement);

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

        /**
         * Initialize all meteor components
         */
        const meteorComponents: (keyof (typeof MeteorImport))[] = [
            'MtBanner',
            'MtLoader',
            'MtProgressBar',
            'MtButton',
            'MtCheckbox',
            'MtColorpicker',
            'MtDatepicker',
            'MtEmailField',
            'MtExternalLink',
            'MtNumberField',
            'MtPasswordField',
            'MtSelect',
            'MtSlider',
            'MtSwitch',
            'MtTextField',
            'MtTextarea',
            'MtUrlField',
            'MtIcon',
            'MtDataTable',
            'MtPagination',
            'MtSkeletonBar',
            'MtToast',
            'MtFloatingUi',
        ];

        // Disable compat for meteor components
        meteorComponents.forEach((componentName) => {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access, max-len
            MeteorImport[componentName].compatConfig = Object.fromEntries(Object.keys(Shopware.compatConfig).map(key => [key, false]));
        });

        meteorComponents.forEach((componentName) => {
            const componentNameAsKebabCase = Shopware.Utils.string.kebabCase(componentName);
            // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
            this.app.component(componentNameAsKebabCase, MeteorImport[componentName]);
        });

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
    createComponent(componentName: string): Promise<App<Element>> {
        return new Promise((resolve) => {
            // load sync components directly
            if (Component.isSyncComponent && Component.isSyncComponent(componentName)) {
                const resolvedComponent = this.componentResolver(componentName);

                if (resolvedComponent === undefined) {
                    return;
                }

                void resolvedComponent.then((component) => {
                    let vueComponent;

                    if (typeof component !== 'boolean') {
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
                        this.app?.component(componentName, component);
                        vueComponent = this.app?.component(componentName);
                    }

                    // @ts-expect-error - resolved config does not match completely a standard vue component
                    this.vueComponents[componentName] = vueComponent;
                    resolve(vueComponent as unknown as App<Element>);
                });

                return;
            }

            // load async components
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
            this.app?.component(componentName, defineAsyncComponent({
                // the loader function
                // @ts-expect-error - resolved config does not match completely a standard vue component
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
            const vueComponent = this.app?.component(componentName);

            // @ts-expect-error - resolved config does not match completely a standard vue component
            this.vueComponents[componentName] = vueComponent;

            resolve(vueComponent as unknown as App<Element>);
        });
    }

    componentResolver(componentName: string) {
        if (!this.resolvedComponentConfigs.has(componentName)) {
            this.resolvedComponentConfigs.set(componentName, new Promise((resolve) => {
                void Component.build(componentName).then((componentConfig) => {
                    if (typeof componentConfig === 'boolean') {
                        resolve(false);
                    } else {
                        this.resolveMixins(componentConfig);
                    }

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

        // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
        this.app?.component(componentName, componentConfig);
        const vueComponent = this.app?.component(componentName);

        // @ts-expect-error - resolved config does not match completely a standard vue component
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

        return this.vueComponents[componentName] as App<Element>;
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
        return this.app;
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
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    setReactive(target: any, propertyName: string, value: unknown) {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        target[propertyName] = value;

        // eslint-disable-next-line @typescript-eslint/no-unsafe-return, @typescript-eslint/no-unsafe-member-access
        return target[propertyName];
    }

    /**
     * Returns the Vue.delete function
     */
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    deleteReactive(target: any, propertyName: string) {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        delete target[propertyName];
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
        VuePlugins.forEach((plugin) => {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            if (plugin?.install?.installed) {
                return;
            }

            // eslint-disable-next-line @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-argument
            this.app?.use(plugin);
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
            this.app?.directive(name, directive);
        });

        return true;
    }

    /**
     * Initialises the standard locales.
     */
    initLocales(store: VuexStore<VuexRootState>) {
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
        const i18n = createI18n(options);

        store.subscribe(({ type }, state) => {
            if (type === 'setAdminLocale') {
                // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
                i18n.global.locale = state.session.currentLocale!;
            }
        });

        this.setLocaleFromUser(store);

        // watch for changes of the user to update the locale
        Shopware.State.watch(state => state.session.currentUser, (newValue, oldValue) => {
            const currentUserLocaleId = newValue?.localeId;
            const oldUserLocaleId = oldValue?.localeId;

            if (currentUserLocaleId && currentUserLocaleId !== oldUserLocaleId) {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
                Shopware.Service('localeHelper').setLocaleWithId(currentUserLocaleId);
            }
        }, { deep: true });

        return i18n;
    }

    setLocaleFromUser(store: VuexStore<VuexRootState>) {
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
    initTitle(app: App<Element>) {
        app.config.globalProperties.$createTitle = function createTitle(
            this: ComponentPublicInstance,
            identifier: string | null = null,
            ...additionalParams
        ): string {
            if (!this.$root) {
                return '';
            }

            const baseTitle = this.$root.$tc('global.sw-admin-menu.textShopwareAdmin');

            if (!this.$route.meta || !this.$route.meta.$module) {
                return '';
            }

            // @ts-expect-error - $module is not typed correctly
            const moduleTitle = this.$route.meta.$module?.title as string;
            const pageTitle = this.$root.$tc(moduleTitle);

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
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        if (componentConfig.mixins?.length) {
            // eslint-disable-next-line max-len
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call
            componentConfig.mixins = componentConfig.mixins.map((mixin) => {
                if (typeof mixin === 'string') {
                    // @ts-expect-error
                    return Mixin.getByName(mixin);
                }

                // eslint-disable-next-line @typescript-eslint/no-unsafe-return
                return mixin;
            });
        }

        if (componentConfig.extends) {
            // @ts-expect-error - extends can be a string or a component config
            this.resolveMixins(componentConfig.extends);
        }
    }
}
