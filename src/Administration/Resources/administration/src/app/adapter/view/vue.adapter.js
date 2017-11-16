import 'src/app/component/components';


import Vue from 'vue';
import VueRouter from 'vue-router';
import utils from 'src/core/service/util.service';
import VueMoment from 'vue-moment';

const vueComponents = {};

export default function VueAdapter(context) {
    Vue.use(VueRouter);
    Vue.use(VueMoment);

    Vue.filter('image', (value) => {
        if (!value) {
            return '';
        }

        return `${context.assetsPath}${value}`;
    });

    Vue.filter('currency', (value, format = 'EUR') => {
        return utils.currency(value, format);
    });

    Vue.filter('date', (value) => {
        return utils.date(value);
    });

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
     * @returns {*}
     */
    function createInstance(renderElement, router, providers) {
        const components = getComponents();

        return new Vue({
            el: renderElement,
            router,
            components,
            template: '<sw-admin />',
            provide() {
                return providers;
            }
        });
    }

    /**
     * Initializes all core components as Vue components.
     *
     * @returns {*}
     */
    function initComponents() {
        const componentRegistry = Shopware.ComponentFactory.getComponentRegistry();

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
     * @returns {*}
     */
    function createComponent(componentName) {
        const componentConfig = Shopware.ComponentFactory.build(componentName);

        if (!componentConfig) {
            return false;
        }

        const vueComponent = Vue.component(componentName, componentConfig);

        vueComponents[componentName] = vueComponent;

        return vueComponent;
    }

    /**
     * Returns a final Vue component by its name.
     *
     * @param componentName
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
     * @returns {Object}
     */
    function getComponents() {
        return vueComponents;
    }

    function getWrapper() {
        return Vue;
    }

    function getName() {
        return 'Vue.js';
    }
}
