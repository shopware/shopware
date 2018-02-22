import VueRouter from 'vue-router';
import RouterFactory from 'src/core/factory/router.factory';
import coreRoutes from 'src/app/routes';

export default function initializeRouter(container) {
    const factoryContainer = this.getContainer('factory');
    const loginService = this.getContainer('service').loginService;
    const factory = RouterFactory(VueRouter, container.view, factoryContainer.module, loginService);
    factory.addRoutes(coreRoutes);
    factory.addModuleRoutes(container.coreModuleRoutes);

    return factory.createRouterInstance();
}
