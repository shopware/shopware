import VueAdapter from 'src/app/adapter/view/vue.adapter';
import ViewFactory from 'src/core/factory/view.factory';

/**
 * Initializes the view of the application
 *
 * @param container
 * @returns {ViewFactory}
 */
export default function initializeView(container) {
    const factoryContainer = this.getContainer('factory');

    const adapter = VueAdapter(
        container.contextService,
        factoryContainer.component,
        factoryContainer.state,
        factoryContainer.filter,
        factoryContainer.directive
    );
    const viewFactory = ViewFactory(adapter);

    viewFactory.initComponents();
    return viewFactory;
}
