/**
 * @package admin
 */

import VueRouter from 'vue-router';
import coreRoutes from 'src/app/route';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeRouter(container) {
    const RouterFactory = Shopware.Classes._private.RouterFactory;
    const factoryContainer = this.getContainer('factory');
    const loginService = Shopware.Service('loginService');
    const factory = RouterFactory(VueRouter, container.view, factoryContainer.module, loginService);

    factory.addRoutes(coreRoutes);

    return factory;
}
