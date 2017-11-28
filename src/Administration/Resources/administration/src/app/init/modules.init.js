/* global Shopware */
import coreModules from 'module';

const ModuleFactory = Shopware.Module;

export default function initializeCoreModules() {
    // Loop through the core modules and register them in the application
    coreModules.forEach((module) => {
        ModuleFactory.register(module, 'core');
    });

    // Return the module routes for the router
    return ModuleFactory.getRoutes();
}
