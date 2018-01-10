/**
 * @module core/factory/view
 */

/**
 * Creates the view factory based on the provided ViewAdapter
 * @method createViewFactory
 * @param {VueAdapter} viewAdapter
 * @memberOf module:core/factory/view
 * @returns {{}}
 */
export default function createViewFactory(viewAdapter) {
    return {
        name: viewAdapter.getName(),
        wrapper: viewAdapter.getWrapper(),
        createInstance: viewAdapter.createInstance,
        createComponent: viewAdapter.createComponent,
        initComponents: viewAdapter.initComponents,
        getComponent: viewAdapter.getComponent,
        getComponents: viewAdapter.getComponents
    };
}
