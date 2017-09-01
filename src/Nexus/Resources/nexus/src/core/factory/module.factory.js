import utils from 'src/core/service/util.service';

export {
    getModuleRoutes,
    registerModule,
    getModuleRegistry
};

/** @type Map modules - Registry for modules */
const modules = new Map();

/**
 * Returns the registry of all modules mounted in the application.
 *
 * @returns {Map} modules - Registry of all modules
 */
function getModuleRegistry() {
    return modules;
}

/**
 * Registers a module in the application. The module will be mounted using
 * the defined routes of the module using the router.
 *
 * @param {Object} module - Module definition - see manifest.js file
 * @param {String} [type=plugin] - Type of the module
 * @returns {Map} moduleRoutes - registered module routes
 */
function registerModule(module, type = 'plugin') {
    const moduleRoutes = new Map();
    const moduleId = module.id;

    // A module should always have an unique identifier cause overloading modules can cause unexpected side effects
    if (!moduleId) {
        utils.warn(
            'ModuleFactory',
            'Module has no unique identifier "id"',
            module
        );
    }

    // Modules will be mounted using the routes definition in the manifest file. If the module doesn't contains a routes
    // definition it's not accessible in the application.
    if (!Object.prototype.hasOwnProperty.call(module, 'routes')) {
        utils.warn(
            'ModuleFactory',
            `Module "${moduleId}" has no configured routes. The module will not be accessible in the administration UI.`,
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

        const componentList = {};
        if (route.components && Object.keys(route.components).length) {
            Object.keys(route.components).forEach((componentKey) => {
                const component = route.components[componentKey];
                componentList[componentKey] = component.name;
            });

            route.components = componentList;
        } else {
            route.components = {
                default: route.component.name
            };

            // Remove the component cause we remapped it to the components object of the route object
            delete route.component;
        }

        // Alias support
        if (route.alias && route.alias.length > 0) {
            route.alias = `/${type}/${route.alias}`;
        }

        moduleRoutes.set(route.name, route);
    });

    const moduleDefinition = {
        routes: moduleRoutes,
        manifest: module
    };

    if (Object.prototype.hasOwnProperty.bind(module, 'navigation') && module.navigation) {
        moduleDefinition.navigation = module.navigation;
    }

    modules.set(moduleId, moduleDefinition);
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
        module.routes.forEach((route) => {
            moduleRoutes.push(route);
        });
    });
    return moduleRoutes;
}
