/* global Shopware */
// ToDo: Still race conditions with app and modules
import 'src/app/state';
import 'src/app/mixin';
import 'module';

export default function initializeCoreModules() {
    const factoryContainer = this.getContainer('factory');
    const moduleFactory = factoryContainer.module;

    // Return the module routes for the router
    return moduleFactory.getModuleRoutes();
}
