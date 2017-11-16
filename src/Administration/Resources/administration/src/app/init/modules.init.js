/* global Shopware */
import coreModules from 'module';

const ModuleFactory = Shopware.ModuleFactory;

export default function initializeCoreModules() {
    // Loop through the core modules and register them in the application
    coreModules.forEach((module) => {
        ModuleFactory.registerModule(module, 'core');
    });

    // Return the module routes for the router
    return ModuleFactory.getModuleRoutes();
}
