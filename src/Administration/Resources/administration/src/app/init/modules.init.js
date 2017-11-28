/* global Shopware */
import 'module';

const ModuleFactory = Shopware.Module;

export default function initializeCoreModules() {
    // Return the module routes for the router
    return ModuleFactory.getRoutes();
}
