import coreModules from 'module';

export default function initializeCoreModules(app, configuration, done) {
    // Loop through the core modules
    coreModules.forEach((module) => {
        Shopware.ModuleFactory.registerModule(module, 'core');
    });

    configuration.coreModuleRoutes = Shopware.ModuleFactory.getModuleRoutes();

    done(configuration);
}
