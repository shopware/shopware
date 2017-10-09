import VueRouter from 'vue-router';
import RouterFactory from 'src/core/factory/router.factory';
import coreRoutes from 'src/app/routes';

export default function initializeRouter(app, configuration, done) {
    let router = RouterFactory(VueRouter, configuration.view);
    router.addRoutes(coreRoutes);
    router.addModuleRoutes(configuration.coreModuleRoutes);

    router = router.createRouterInstance();
    configuration.router = router;

    done(configuration);
}
