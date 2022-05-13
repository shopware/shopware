/**
 * @module app/adapter/view/vue
 */
import ViewAdapter from 'src/core/adapter/view.adapter';

import Vue from 'vue';
import VueRouter from 'vue-router';
import VueI18n from 'vue-i18n';
import VueMeta from 'vue-meta';
import VuePlugins from 'src/app/plugin';
import setupShopwareDevtools from 'src/app/adapter/view/sw-vue-devtools';

const { Component, State, Mixin } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class VueAdapter extends ViewAdapter {
    /**
     * @constructor
     */
    constructor(Application) {
        super(Application);

        this.Application = Application;
        this.applicationFactory = Application.getContainer('factory');

        this.componentFactory = this.applicationFactory.component;
        this.stateFactory = this.applicationFactory.state;
        this.localeFactory = this.applicationFactory.locale;
        this.root = null;
        this.resolvedComponentConfigs = new Map();

        this.vueComponents = {};
    }

    /**
     * Creates the main instance dfsffor the view layer.
     * Is used on startup process of the main application.
     *
     * @param renderElement
     * @param router
     * @param providers
     * @memberOf module:app/adapter/view/vue
     * @returns {Vue}
     */
    init(renderElement, router, providers) {
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
                return providers;
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
     *
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
        initContainer.router.addModuleRoutes(allRoutes);

        // create routes for core and plugins
        initContainer.router.createRouterInstance();
    }


    /**
     * Initializes all core components as Vue components.
     *
     * @memberOf module:app/adapter/view/vue
     * @returns {Object}
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
     *
     * @memberOf module:app/adapter/view/vue
     * @returns {Object}
     */
    initModuleLocales() {
        // Extend default snippets with module specific snippets
        const moduleSnippets = this.applicationFactory.module.getModuleSnippets();

        Object.keys(moduleSnippets).forEach((key) => {
            this.applicationFactory.locale.extend(key, moduleSnippets[key]);
        });

        return this.applicationFactory.locale;
    }

    /**
     * Returns the component as a Vue component.
     * Includes the full rendered template with all overrides.
     *
     * @param componentName
     * @memberOf module:app/adapter/view/vue
     * @returns {Vue}
     */
    createComponent(componentName) {
        return new Promise(async (resolve) => {
            // load sync components directly
            if (Component.isSyncComponent && Component.isSyncComponent(componentName)) {
                const resolvedComponent = await this.componentResolver(componentName).component;
                const vueComponent = Vue.component(componentName, resolvedComponent);

                this.vueComponents[componentName] = vueComponent;
                resolve(vueComponent);
                return;
            }

            // load async components
            const vueComponent = Vue.component(componentName, () => this.componentResolver(componentName));
            this.vueComponents[componentName] = vueComponent;

            resolve(vueComponent);
        });
    }

    componentResolver(componentName) {
        if (!this.resolvedComponentConfigs.has(componentName)) {
            this.resolvedComponentConfigs.set(componentName, ({
                component: new Promise(async (resolve) => {
                    const componentConfig = await Component.build(componentName);
                    this.resolveMixins(componentConfig);

                    resolve(componentConfig);
                }),
                loading: {
                    functional: true,
                    render(e) {
                        return e('div', {
                            style: { display: 'none' },
                        });
                    },
                },
                delay: 0,
            }));
        }

        return this.resolvedComponentConfigs.get(componentName);
    }

    /**
     * Builds and creates a Vue component using the provided component configuration.
     *
     * @param {Object }componentConfig
     * @memberOf module:app/adapter/view/vue
     * @returns {Function}
     */
    buildAndCreateComponent(componentConfig) {
        const componentName = componentConfig.name;
        this.resolveMixins(componentConfig);

        const vueComponent = Vue.component(componentConfig.name, componentConfig);
        this.vueComponents[componentName] = vueComponent;

        return vueComponent;
    }

    /**
     * Returns a final Vue component by its name.
     *
     * @param componentName
     * @memberOf module:app/adapter/view/vue
     * @returns {null|Vue}
     */
    getComponent(componentName) {
        if (!this.vueComponents[componentName]) {
            return null;
        }

        return this.vueComponents[componentName];
    }

    /**
     * Returns the complete set of available Vue components.
     *
     * @memberOf module:app/adapter/view/vue
     */
    getComponents() {
        return this.vueComponents;
    }

    /**
     * Returns the adapter wrapper
     *
     * @memberOf module:app/adapter/view/vue
     * @returns {Vue}
     */
    getWrapper() {
        return Vue;
    }

    /**
     * Returns the name of the adapter
     *
     * @memberOf module:app/adapter/view/vue
     * @returns {string}
     */
    getName() {
        return 'Vue.js';
    }

    /**
     * Returns the Vue.set function
     *
     * @memberOf module:app/adapter/view/vue
     */
    setReactive(target, propertyName, value) {
        return Vue.set(target, propertyName, value);
    }

    /**
     * Returns the Vue.delete function
     *
     * @memberOf module:app/adapter/view/vue
     * @returns {() => void}
     */
    deleteReactive(target, propertyName) {
        return Vue.delete(target, propertyName);
    }

    /**
     * Private methods
     */

    /**
     * Initialises all plugins for VueJS
     *
     * @private
     * @memberOf module:app/adapter/view/vue
     */
    initPlugins() {
        // Add the community plugins to the plugin list
        VuePlugins.push(VueRouter, VueI18n, VueMeta);
        VuePlugins.forEach((plugin) => {
            Vue.use(plugin);
        });

        return true;
    }

    /**
     * Initializes all custom directives.
     *
     * @private
     * @memberOf module:app/adapter/view/vue
     * @returns {Boolean}
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
     * @memberOf module:app/adapter/view/vue
     * @returns {Boolean}
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
     *
     * @private
     * @memberOf module:app/adapter/view/vue
     * @return {VueI18n}
     */
    initLocales(store) {
        const registry = this.localeFactory.getLocaleRegistry();
        const messages = {};
        const fallbackLocale = Shopware.Context.app.fallbackLocale;

        registry.forEach((localeMessages, key) => {
            store.commit('registerAdminLocale', key);
            messages[key] = localeMessages;
        });

        const lastKnownLocale = this.localeFactory.getLastKnownLocale();
        store.dispatch('setAdminLocale', lastKnownLocale);

        const i18n = new VueI18n({
            locale: lastKnownLocale,
            fallbackLocale,
            silentFallbackWarn: true,
            sync: true,
            messages,
        });

        store.subscribe(({ type }, state) => {
            if (type === 'setAdminLocale') {
                i18n.locale = state.session.currentLocale;
            }
        });

        this.setLocaleFromUser(store);

        return i18n;
    }

    setLocaleFromUser(store) {
        const currentUser = store.state.session.currentUser;

        if (currentUser) {
            const userLocaleId = currentUser.localeId;
            Shopware.Service('localeHelper').setLocaleWithId(userLocaleId);
        }
    }

    /**
     * Extends Vue prototype to access $createTitle function
     *
     * @private
     * @memberOf module:app/adapter/view/vue
     */
    initTitle() {
        if (Vue.prototype.hasOwnProperty('$createTitle')) {
            return;
        }

        /**
         * Generates the document title out of the given VueComponent and parameters
         *
         * @param {String} [identifier = null]
         * @param {...String} additionalParams
         * @returns {string}
         */
        Vue.prototype.$createTitle = function createTitle(identifier = null, ...additionalParams) {
            const baseTitle = this.$root.$tc('global.sw-admin-menu.textShopwareAdmin');

            if (!this.$route.meta || !this.$route.meta.$module) {
                return '';
            }

            const pageTitle = this.$root.$tc(this.$route.meta.$module.title);

            const params = [baseTitle, pageTitle, identifier, ...additionalParams].filter((item) => {
                return item !== null && item.trim() !== '';
            });

            return params.reverse().join(' | ');
        };
    }

    /**
     * Recursively resolves mixins referenced by name
     *
     * @private
     * @memberOf module:app/adapter/view/vue
     */
    resolveMixins(componentConfig) {
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
            this.resolveMixins(componentConfig.extends);
        }
    }
}
