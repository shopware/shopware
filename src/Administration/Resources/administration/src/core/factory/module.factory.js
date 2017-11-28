import utils from 'src/core/service/util.service';

export default {
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
 * @returns {Boolean|Object} moduleDefinition - registered module definition
 */
function registerModule(module, type = 'plugin') {
    const moduleRoutes = new Map();
    const moduleId = module.id;

    // A module should always have an unique identifier cause overloading modules can cause unexpected side effects
    if (!moduleId) {
        utils.warn(
            'ModuleFactory',
            'Module has no unique identifier "id". Abort registration.',
            module
        );
        return false;
    }

    if (modules.has(moduleId)) {
        utils.warn(
            'ModuleFactory',
            `A module with the identifier "${moduleId}" is registered already. Abort registration.`,
            modules.get(moduleId)
        );

        return false;
    }

    const splitModuleId = moduleId.split('.');

    if (splitModuleId.length < 2) {
        utils.warn(
            'ModuleFactory',
            'Module identifier does not match the necessary format "[section].[name]":',
            moduleId,
            'Abort registration.'
        );
        return false;
    }

    // Modules will be mounted using the routes definition in the manifest file. If the module doesn't contains a routes
    // definition it isn't accessible in the application.
    if (!Object.prototype.hasOwnProperty.call(module, 'routes')) {
        utils.warn(
            'ModuleFactory',
            `Module "${moduleId}" has no configured routes. The module will not be accessible in the administration UI.`,
            'Abort registration.',
            module
        );
        return false;
    }

    // Sanitize the modules routes
    Object.keys(module.routes).forEach((routeKey) => {
        const route = module.routes[routeKey];

        // Rewrite name and path
        route.name = `${moduleId}.${routeKey}`;
        route.path = `/${splitModuleId.join('/')}/${route.path}`;
        route.type = type;

        const componentList = {};
        if (route.components && Object.keys(route.components).length) {
            Object.keys(route.components).forEach((componentKey) => {
                const component = route.components[componentKey];

                // Don't register a component without a name
                if (Object.prototype.hasOwnProperty(component, 'name')
                || !component.name
                || !component.name.length) {
                    utils.warn(
                        'ModuleFactory',
                        `Component ${component} has no "name" property. The component will not be registered.`
                    );

                    return;
                }

                componentList[componentKey] = component.name;
            });

            route.components = componentList;
        } else {
            if (!route.component || !route.component.name) {
                utils.warn(
                    'ModuleFactory',
                    `The route definition of module "${moduleId}" is not valid. A route needs an assigned component.`
                );
                return;
            }
            route.components = {
                default: route.component.name
            };

            // Remove the component cause we remapped it to the components object of the route object
            delete route.component;
        }

        // Alias support
        if (route.alias && route.alias.length > 0) {
            route.alias = `/${splitModuleId.join('/')}/${route.alias}`;
        }

        moduleRoutes.set(route.name, route);
    });

    // When we're not having at least one valid route definition we're not registering the module
    if (moduleRoutes.size === 0) {
        utils.warn(
            'ModuleFactory',
            `The module "${moduleId}" was not registered cause it hasn't a valid route definition`,
            'Abort registration.',
            module.routes
        );
        return false;
    }

    const moduleDefinition = {
        routes: moduleRoutes,
        manifest: module,
        type
    };

    // Add the navigation of the module to the module definition. We'll create a menu entry later on
    if (Object.prototype.hasOwnProperty.bind(module, 'navigation') && module.navigation) {
        moduleDefinition.navigation = module.navigation;
    }

    modules.set(moduleId, moduleDefinition);

    return moduleDefinition;
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
