import Vue from 'vue';
import VueRouter from 'vue-router';
import 'src/app/component/components';

const vueComponents = {};

export default function VueAdapter(context) {
    Vue.use(VueRouter);

    Vue.filter('image', (value) => {
        if (!value) {
            return '';
        }

        return `${context.assetsPath}${value}`;
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
        const componentRegistry = Shopware.ComponentFactory.getComponentRegistry();

        if (!componentRegistry.has(componentName)) {
            return false;
        }

        const componentConfig = componentRegistry.get(componentName);

        /**
         * Get the final template result including all overrides.
         */
        componentConfig.template = ComponentFactory.getComponentTemplate(componentName);

        const vueComponent = Vue.component(componentName, componentConfig);

        vueComponents[componentName] = vueComponent;

        return vueComponent;
    }

    /**
     * Returns a final Vue component by its name.
     *
     * @param componentName
     * @returns {*}
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
     * @returns {{}}
     */
    function getComponents() {
        return vueComponents;
    }

    function getWrapper() {
        return Vue;
    }

    function getName() {
        return 'Vue';
    }
}
