/**
 * @package admin
 */

// Vue3 imports
import * as VueRouter3 from 'vue-router_v3';

// Vue2 imports
import VueRouter from 'vue-router';

import coreRoutes from 'src/app/route';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeRouter(container: InitContainer) {
    // @ts-expect-error
    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
    const vue3 = !!window._features_?.vue3;
    const RouterFactory = Shopware.Classes._private.RouterFactory;
    const factoryContainer = Shopware.Application.getContainer('factory');
    const loginService = Shopware.Service('loginService');
    let factory;
    if (vue3) {
        // @ts-expect-error - RouterFactory is also a method
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
        factory = RouterFactory(VueRouter3, container.view, factoryContainer.module, loginService);
    } else {
        // @ts-expect-error - RouterFactory is also a method
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
        factory = RouterFactory(VueRouter, container.view, factoryContainer.module, loginService);
    }


    // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
    factory.addRoutes(coreRoutes);

    // eslint-disable-next-line @typescript-eslint/no-unsafe-return
    return factory;
}
