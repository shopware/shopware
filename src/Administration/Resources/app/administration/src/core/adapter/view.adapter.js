const { warn } = Shopware.Utils.debug;

/**
 * View Adapter Boilerplate class which provides a blueprint for view adapters (like for React, VueJS, ...)
 * @class
 */
export default class ViewAdapter {
    /**
     * @constructor
     */
    constructor(Application) {
        this.Application = Application;
        this.applicationFactory = Application.getContainer('factory');

        this.componentFactory = this.applicationFactory.component;
        this.stateFactory = this.applicationFactory.state;
        this.localeFactory = this.applicationFactory.locale;
        this.root = null;
    }


    /**
     * Creates the main instance for the view layer.
     * Is used on startup process of the main application.
     *
     * @param renderElement
     * @param router
     * @param providers
     * @memberOf module:app/adapter/view/vue
     * @returns {object}
     */
    init(renderElement, router, providers) {
        warn(
            'init',
            `
                You need to overwrite the init method which expect these arguments: 
                ${renderElement}
                ${router}
                ${providers}
            `,
        );

        return this.root;
    }

    /**
     * Initializes all core components as Vue components.
     *
     * @memberOf module:app/adapter/view/vue
     * @returns {Object}
     */
    initComponents(renderElement, router, providers) {
        warn(
            'initComponents',
            `
                You need to overwrite the initComponents method which expect these arguments: 
                ${renderElement}
                ${router}
                ${providers}
            `,
        );
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
        warn(
            'createComponent',
            `
                You need to overwrite the createComponent method which expect these arguments: 
                ${componentName}
            `,
        );
    }

    /**
     * Returns a final Vue component by its name.
     *
     * @param componentName
     * @memberOf module:app/adapter/view/vue
     * @returns {null|Component}
     */
    getComponent(componentName) {
        warn(
            'getComponent',
            `
                You need to overwrite the getComponent method which expect these arguments: 
                ${componentName}
            `,
        );
    }

    /**
     * Returns the complete set of available Vue components.
     *
     * @memberOf module:app/adapter/view/vue
     * @returns {Object}
     */
    getComponents() {
        warn(
            'getComponents',
            'You need to overwrite the getComponents method',
        );
    }

    /**
     * Returns the adapter wrapper
     *
     * @memberOf module:app/adapter/view/vue
     * @returns {Vue}
     */
    getWrapper() {
        warn(
            'getWrapper',
            'You need to overwrite the getWrapper method',
        );
    }

    /**
     * Returns the name of the adapter
     *
     * @memberOf module:app/adapter/view/vue
     * @returns {string}
     */
    getName() {
        warn(
            'getName',
            'You need to overwrite the getName method',
        );
    }

    /**
     * Returns the Vue.set function
     *
     * @memberOf module:app/adapter/view/vue
     * @returns {function}
     */
    setReactive() {
        warn(
            'setReactive',
            'You need to overwrite the setReactive method',
        );
    }

    /**
     * Returns the Vue.delete function
     *
     * @memberOf module:app/adapter/view/vue
     * @returns {function}
     */
    deleteReactive() {
        warn(
            'deleteReactive',
            'You need to overwrite the deleteReactive method',
        );
    }
}
