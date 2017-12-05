export default function createRouter(Router, View, moduleFactory, LoginService) {
    let allRoutes = [];
    let moduleRoutes = [];

    return {
        addRoutes,
        addModuleRoutes,
        createRouterInstance,
        getViewComponent
    };

    /**
     * Creates the router instance for the application.
     *
     * @param {Object} [opts={}]
     * @returns {VueRouter} router
     */
    function createRouterInstance(opts = {}) {
        const mergedRoutes = registerModuleRoutesAsChildren(allRoutes, moduleRoutes);

        const options = Object.assign({}, opts, {
            routes: mergedRoutes
        });

        const router = new Router(options);

        beforeRouterInterceptor(router);
        return router;
    }

    /**
     * Installs the navigation guard interceptor which provides every route, if possible, with the module definition.
     * This is useful to generalize the route managing.
     *
     * @param {VueRouter} router
     * @returns {VueRouter} router
     */
    function beforeRouterInterceptor(router) {
        router.beforeEach((to, from, next) => {
            const bearerAuthExpiry = LoginService.getExpiry();
            const loggedIn = LoginService.validateExpiry(bearerAuthExpiry);

            // The login route will be called and the user is not logged in, let him see the login
            if ((to.name === 'login' || to.path === '/login') && !loggedIn) {
                return next();
            }

            // The login route will be called and the user is not logged in, redirect to the dashboard
            if ((to.name === 'login' || to.path === '/login') && loggedIn) {
                return next({ name: 'core' });
            }

            // User tries to access a protected route, therefore redirect him to the login
            if (!loggedIn) {
                return next({ name: 'sw.login.index' });
            }

            // Provide information about the module
            const moduleRegistry = moduleFactory.getModuleRegistry();

            // Just get the first part of the name as the namespace
            // The first part should be the module (e.g. core or plugin)
            // The second part is the module indicator (index, list, detail etc.)
            let moduleNamespace = to.name.split('.');
            moduleNamespace = `${moduleNamespace[0]}-${moduleNamespace[1]}`;

            // If the module namespace isn't registered, we let the router follow the route
            if (!moduleRegistry.has(moduleNamespace)) {
                return next();
            }

            // Just make sure the route name is matching the registered name to ensure we're injecting the correct
            // module into the route definition
            const module = moduleRegistry.get(moduleNamespace);
            if (!module.routes.has(to.name)) {
                return next();
            }

            to.meta.$module = module.manifest;
            return next();
        });

        return router;
    }

    /**
     * Registers the module routes as child routes of the root core route to automatically
     * providing the administration base structure to every module.
     *
     * @param {Array} core - Core routes
     * @param {Array} module - Module routes
     * @returns {Array} core - new core routes definition
     */
    function registerModuleRoutesAsChildren(core, module) {
        const moduleRootRoutes = [];
        const moduleNormalRoutes = [];

        // Separate core routes from the normal routes
        module.forEach((moduleRoute) => {
            if (moduleRoute.coreRoute === true) {
                moduleRootRoutes.push(moduleRoute);
                return;
            }

            moduleNormalRoutes.push(moduleRoute);
        });

        core.map((route) => {
            if (route.root === true) {
                route.children = moduleNormalRoutes;
            }

            return route;
        });

        // Merge the module core routes with the routes from the routes file
        core = [...core, ...moduleRootRoutes];
        return core;
    }

    /**
     * Registers the core module routes. The provided component name will be remapped to the corresponding
     * view component.
     *
     * @param {Array} routes
     * @returns {Array} moduleRoutes - converted routes array
     */
    function addModuleRoutes(routes) {
        routes.map((route) => {
            return convertRouteComponentToViewComponent(route);
        });

        moduleRoutes = [...moduleRoutes, ...routes];

        return moduleRoutes;
    }

    /**
     * Registers module routes to the router. The method will loop through the provided routes
     * and remaps the component names (e.g. either `route.component` or `route.components`) to
     * the corresponding view component which should be registered under the same name.
     *
     * @param {Array} routes
     * @returns {Array} allRoutes - converted routes array
     */
    function addRoutes(routes) {
        routes.map((route) => {
            return convertRouteComponentToViewComponent(route);
        });

        allRoutes = [...allRoutes, ...routes];

        return allRoutes;
    }

    /**
     * Converts the `route.component` / `route.components` property which is usually a component name
     * to a view component, so the router works with component instead of looking up component names
     * in the internal registry of the view framework.
     *
     * @param {Object} route - Route definition
     * @returns {Object} route - Converted route definition
     */
    function convertRouteComponentToViewComponent(route) {
        if (Object.prototype.hasOwnProperty.call(route, 'components') && Object.keys(route.components).length) {
            const componentList = {};

            Object.keys(route.components).forEach((componentKey) => {
                let component = route.components[componentKey];

                // Just convert component names
                if (typeof component === 'string') {
                    component = getViewComponent(component);
                }
                componentList[componentKey] = component;
            });
            route.components = componentList;
        }

        if (typeof route.component === 'string') {
            route.component = getViewComponent(route.component);
        }

        return route;
    }

    /**
     * Get a component using the argument `componentName` from the view layer.
     *
     * @param {String} componentName
     * @returns {Vue|null} - View component or null
     */
    function getViewComponent(componentName) {
        return View.getComponent(componentName);
    }
}
