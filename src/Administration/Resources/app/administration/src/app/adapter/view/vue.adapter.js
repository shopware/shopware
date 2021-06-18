/**
 * @module app/adapter/view/vue
 */
import ViewAdapter from 'src/core/adapter/view.adapter';

import Vue from 'vue';
import VueRouter from 'vue-router';
import VueI18n from 'vue-i18n';
import VueMeta from 'vue-meta';
import VuePlugins from 'src/app/plugin';

const { Component, State, Mixin } = Shopware;

export default class VueAdapter extends ViewAdapter {
    /**
     * @constructor
     */
    constructor(Application) {
        super(Application);

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

        return this.root;
    }

    /**
     * Initialize of all dependencies.
     *
     * @memberOf module:app/adapter/view/vue
     * @returns {Object}
     */
    initDependencies() {
        const initContainer = this.Application.getContainer('init');

        // initialize all components
        this.initComponents();

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
    initComponents() {
        const componentRegistry = this.componentFactory.getComponentRegistry();
        this.componentFactory.resolveComponentTemplates();

        componentRegistry.forEach((component) => {
            this.createComponent(component.name);
        });

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
     * @returns {Function}
     */
    createComponent(componentName) {
        const componentConfig = Component.build(componentName);

        if (!componentConfig) {
            return false;
        }

        this.resolveMixins(componentConfig);

        const vueComponent = Vue.component(componentName, componentConfig);
        this.vueComponents[componentName] = vueComponent;

        return vueComponent;
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
     * @returns {null|Component}
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
     * @returns {Object}
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
     * @returns {function}
     */
    setReactive(target, propertyName, value) {
        return Vue.set(target, propertyName, value);
    }

    /**
     * Returns the Vue.delete function
     *
     * @memberOf module:app/adapter/view/vue
     * @returns {function}
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
