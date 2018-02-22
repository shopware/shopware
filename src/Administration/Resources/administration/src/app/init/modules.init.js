import 'module';

export default function initializeCoreModules() {
    const factoryContainer = this.getContainer('factory');
    const moduleFactory = factoryContainer.module;

    // Return the module routes for the router
    return moduleFactory.getModuleRoutes();
}
