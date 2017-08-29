export {
    getModuleRoutes,
    registerModule
};

/** @type Map modules - Registry for modules */
const modules = new Map();

/**
 * Registers a module in the application. The module will be mounted using
 * the defined routes of the module using the router.
 *
 * @param {Object} module - Module definition - see manifest.js file
 * @param {String} [type=plugin] - Type of the module
 * @returns {Array} registered module routes
 */
function registerModule(module, type = 'plugin') {
    const moduleRoutes = [];
    const moduleId = module.id;

    // A module should always have an unique identifier cause overloading modules can cause unexpected side effects
    if (!moduleId) {
        console.warn('[module.factory] Module has no unique identifier', module);
    }

    // Modules will be mounted using the routes definition in the manifest file. If the module doesn't contains a routes
    // definition it's not accessible in the application.
    if (!Object.prototype.hasOwnProperty.call(module, 'routes')) {
        console.warn(
            `[module.factory] Module "${moduleId}" has no defined routes. The module will not be accessible.`,
            module
        );
        return moduleRoutes;
    }

    // Sanitize the modules routes
    Object.keys(module.routes).forEach((routeKey) => {
        const route = module.routes[routeKey];

        // Rewrite name and path
        route.name = `${moduleId}.${routeKey}`;
        route.path = `/${type}/${route.path}`;
        route.type = type;
        route.component = route.component.name;

        // Alias support
        if (route.alias && route.alias.length > 0) {
            route.alias = `/${type}/${route.alias}`;
        }

        moduleRoutes.push(route);
    });

    // console.log('set module routes', moduleRoutes);
    modules.set(moduleId, moduleRoutes);
    return moduleRoutes;
}

/**
 * Returns the defined module routes which will be registered in the router and therefore will be accessible in the
 * application.
 *
 * @returns {Array} route definitions - see {@link https://router.vuejs.org/en/essentials/named-routes.html}
 */
function getModuleRoutes() {
    const moduleRoutes = [];

    modules.forEach((module) => {
        module.forEach((route) => {
            moduleRoutes.push(route);
        });
    });
    return moduleRoutes;
}
