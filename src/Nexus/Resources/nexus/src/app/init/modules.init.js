import ModuleFactory from 'src/core/factory/module.factory';

export default function initializeCoreModules(app, configuration, done) {
    configuration.coreModuleRoutes = ModuleFactory.getModuleRoutes();

    done(configuration);
}
