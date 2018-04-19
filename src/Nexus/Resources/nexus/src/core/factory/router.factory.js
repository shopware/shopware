export default function createRouter(Router, View) {
    let allRoutes = [];
    let moduleRoutes = [];

    return {
        addRoutes,
        addModuleRoutes,
        createRouterInstance,
        getViewComponent
    };

    function createRouterInstance(opts) {
        const mergedRoutes = registerModuleRoutesAsChildren(allRoutes, moduleRoutes);
        const options = Object.assign({}, opts, {
            routes: mergedRoutes
        });

        const router = new Router(options);

        // createAuthenticationInterceptor(router);
        return router;
    }

    /* function createAuthenticationInterceptor(router) {
        router.beforeEach((to, from, next) => {
            if (to.name === 'login' || to.path === '/login') {
                return next();
            }

            if (!app.state.state.bearerToken) {
                return next({ path: '/login' });
            }
            return next();
        });
    } */

    function registerModuleRoutesAsChildren(core, module) {
        core.forEach((route) => {
            if (route.root === true && route.coreRoute === true) {
                route.children = module;
            }
        });

        return core;
    }

    function addModuleRoutes(routes) {
        routes.forEach((route) => {
            if (typeof route.component === 'string') {
                route.component = getViewComponent(route.component);
            }
        });

        moduleRoutes = [...moduleRoutes, ...routes];

        return moduleRoutes;
    }

    function addRoutes(routes) {
        routes.forEach((route) => {
            if (typeof route.component === 'string') {
                route.component = getViewComponent(route.component);
            }
        });

        allRoutes = [...allRoutes, ...routes];

        return allRoutes;
    }

    function getViewComponent(componentName) {
        return View.getComponent(componentName);
    }
}
