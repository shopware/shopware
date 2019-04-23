/**
 * @module app/adapter/view/vue
 */
import Vue from 'vue';
import Vuex from 'vuex';
import VueRouter from 'vue-router';
import VueI18n from 'vue-i18n';
import VueMeta from 'vue-meta';
import DeviceHelper from 'src/core/plugins/device-helper.plugin';
import { Component, State, Mixin } from 'src/core/shopware';
import EntityStore from 'src/core/data/EntityStore';
import { warn } from 'src/core/service/utils/debug.utils';

/**
 * Contains the global Vue.js components
 * @type {{}}
 */
const vueComponents = {};

/**
 * Allows to set a object property over Vue
 * @param {Object} object
 * @param {String} property
 * @param {*} value
 * @returns {*}
 */
export function setReactive(object, property, value) {
    return Vue.set(object, property, value);
}

/**
 * Allows to delete a object property over Vue
 * @param {Object} object
 * @param {String} key
 * @returns {*}
 */
export function deleteReactive(object, key) {
    return Vue.delete(object, key);
}

/**
 * @method VueAdapter
 * @memberOf module:app/adapter/view/vue
 * @param context
 * @param componentFactory
 * @param stateFactory
 * @param filterFactory
 * @param directiveFactory
 * @param localeFactory
 * @returns {VueAdapter}
 */
export default function VueAdapter(context, componentFactory, stateFactory, filterFactory, directiveFactory, localeFactory) {
    return {
        createInstance,
        initComponents,
        createComponent,
        getComponent,
        getComponents,
        getWrapper,
        getName
    };

    /**
     * Creates the main instance for the view layer.
     * Is used on startup process of the main application.
     *
     * @param renderElement
     * @param router
     * @param providers
     * @memberOf module:app/adapter/view/vue
     * @returns {Vue}
     */
    function createInstance(renderElement, router, providers) {
        initPlugins();
        initDirectives();
        initFilters();
        initInheritance();
        initTitle();

        const store = initStore();
        const i18n = initLocales(store);

        const components = getComponents();

        // Enable performance measurements in development mode
        Vue.config.performance = process.env.NODE_ENV !== 'production';

        // make all features globally available to templates
        Vue.mixin({
            data() {
                return Shopware.FeatureConfig.getAll();
            }
        });

        return new Vue({
            el: renderElement,
            template: '<sw-admin />',
            router,
            store,
            i18n,
            components,
            data() {
                return {
                    initError: {}
                };
            },
            provide() {
                return providers;
            }
        });
    }

    /**
     * Initializes all core components as Vue components.
     *
     * @memberOf module:app/adapter/view/vue
     * @returns {Object}
     */
    function initComponents() {
        const componentRegistry = componentFactory.getComponentRegistry();

        componentRegistry.forEach((component) => {
            createComponent(component.name);
        });

        return vueComponents;
    }

    /**
     * Returns the component as a Vue component.
     * Includes the full rendered template with all overrides.
     *
     * @param componentName
     * @memberOf module:app/adapter/view/vue
     * @returns {Function}
     */
    function createComponent(componentName) {
        const componentConfig = Component.build(componentName);

        if (!componentConfig) {
            return false;
        }

        // If the mixin is a string, use our mixin registry
        if (componentConfig.mixins && componentConfig.mixins.length) {
            componentConfig.mixins = componentConfig.mixins.map((mixin) => {
                if (typeof mixin === 'string') {
                    return Mixin.getByName(mixin);
                }

                return mixin;
            });
        }

        const vueComponent = Vue.component(componentName, componentConfig);
        vueComponents[componentName] = vueComponent;

        return vueComponent;
    }

    /**
     * Returns a final Vue component by its name.
     *
     * @param componentName
     * @memberOf module:app/adapter/view/vue
     * @returns {null|Component}
     */
    function getComponent(componentName) {
        if (!vueComponents[componentName]) {
            return null;
        }

        return vueComponents[componentName];
    }

    /**
     * Returns the complete set of available Vue components.
     *
     * @memberOf module:app/adapter/view/vue
     * @returns {Object}
     */
    function getComponents() {
        return vueComponents;
    }

    /**
     * Initialises all plugins for VueJS
     *
     * @private
     * @memberOf module:app/adapter/view/vue
     */
    function initPlugins() {
        Vue.use(Vuex);
        Vue.use(VueRouter);
        Vue.use(VueI18n);
        Vue.use(DeviceHelper);
        Vue.use(VueMeta);
    }

    /**
     * Initializes all custom directives.
     *
     * @private
     * @memberOf module:app/adapter/view/vue
     * @returns {Boolean}
     */
    function initDirectives() {
        const registry = directiveFactory.getDirectiveRegistry();

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
    function initFilters() {
        const registry = filterFactory.getRegistry();

        registry.forEach((factoryMethod, name) => {
            Vue.filter(name, factoryMethod);
        });

        return true;
    }

    /**
     * Initializes the Vuex store for local state management
     *
     * @private
     * @memberOf module:app/adapter/view/vue
     * @returns {Vuex.Store}
     */
    function initStore() {
        const store = new Vuex.Store({
            modules: filterStateRegistry(State.getStoreRegistry()),
            strict: false
        });

        // remove unnecessary objects from state.factory
        State.getStoreRegistry().delete('vuex');

        return store;
    }

    /**
     * Returns only parts from state namespace that should be registered at Vuex
     * This will become unnecessary when old data handling is removed
     * @param registry
     */
    function filterStateRegistry(registry) {
        const storeModules = {};
        registry.forEach((value, key) => {
            if (value instanceof EntityStore) {
                return;
            }

            storeModules[key] = value;
        });

        return storeModules;
    }

    /**
     * Initialises the standard locales.
     *
     * @private
     * @memberOf module:app/adapter/view/vue
     * @return {VueI18n}
     */
    function initLocales(store) {
        const registry = localeFactory.getLocaleRegistry();
        const messages = {};

        registry.forEach((localeMessages, key) => {
            store.commit('registerAdminLocale', key);
            messages[key] = localeMessages;
        });

        store.commit('setAdminLocale', localeFactory.getLastKnownLocale());
        store.commit('setAdminFallbackLocale', 'en-GB');

        const i18n = new VueI18n({
            locale: store.state.adminLocale.currentLocale,
            fallbackLocale: store.state.adminLocale.fallbackLocale,
            messages
        });

        store.subscribe(({ type }, state) => {
            if (type === 'setAdminLocale') {
                i18n.locale = state.adminLocale.currentLocale;
                return;
            }

            if (type === 'setAdminFallbackLocale') {
                i18n.fallbackLocale = state.adminLocale.fallbackLocale;
            }
        });

        return i18n;
    }

    /**
     * Extends Vue prototype to access super class for component inheritance.
     *
     * @private
     * @memberOf module:app/adapter/view/vue
     */
    function initInheritance() {
        if (Vue.prototype.hasOwnProperty('$super')) {
            return;
        }

        Object.defineProperties(Vue.prototype, {
            $super: {
                get() {
                    /**
                     * Registers a proxy as the $super property on every instance.
                     * Makes it possible to dynamically access methods of an extended component.
                     */
                    return new Proxy(this, {
                        get(target, key) {
                            /**
                             * Fallback method which will be returned
                             * if the called method does not exist on a super class.
                             */
                            function empty() {
                                warn('View', `The method "${key}" is not defined in any super class.`, target);
                            }

                            /**
                             * Recursively search for a method in super classes.
                             * This enables multi level inheritance.
                             */
                            function getSuperMethod(comp, methodName) {
                                if (comp.extends && comp.extends.methods && comp.extends.methods[methodName]) {
                                    return comp.extends.methods[methodName];
                                }
                                if (comp.extends.extends) {
                                    return getSuperMethod(comp.extends, methodName);
                                }

                                return empty;
                            }

                            return getSuperMethod(target.constructor.options, key).bind(target);
                        }
                    });
                }
            }
        });
    }

    /**
     * Extends Vue prototype to access $createTitle function
     *
     * @private
     * @memberOf module:app/adapter/view/vue
     */
    function initTitle() {
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
            const baseTitle = this.$tc('global.sw-admin-menu.textShopwareAdmin');
            const pageTitle = this.$tc(this.$route.meta.$module.name);

            const params = [baseTitle, pageTitle, identifier, ...additionalParams].filter((item) => {
                return item !== null && item !== '';
            });

            return params.reverse().join(' | ');
        };
    }

    /**
     * Returns the adapter wrapper
     *
     * @memberOf module:app/adapter/view/vue
     * @returns {Vue}
     */
    function getWrapper() {
        return Vue;
    }

    /**
     * Returns the name of the adapter
     *
     * @memberOf module:app/adapter/view/vue
     * @returns {string}
     */
    function getName() {
        return 'Vue.js';
    }
}
