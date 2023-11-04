/**
 * @package admin
 */

import VueRouter from 'vue-router';
import coreRoutes from 'src/app/route';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeRouter(container: InitContainer) {
    const RouterFactory = Shopware.Classes._private.RouterFactory;
    const factoryContainer = Shopware.Application.getContainer('factory');
    const loginService = Shopware.Service('loginService');
    // @ts-expect-error - RouterFactory is also a method
    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
    const factory = RouterFactory(VueRouter, container.view, factoryContainer.module, loginService);

    // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
    factory.addRoutes(coreRoutes);

    // eslint-disable-next-line @typescript-eslint/no-unsafe-return
    return factory;
}
