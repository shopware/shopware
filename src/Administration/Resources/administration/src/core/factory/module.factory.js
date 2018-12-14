/**
 * @module core/factory/module
 */
import { warn } from 'src/core/service/utils/debug.utils';
import { hasOwnProperty } from 'src/core/service/utils/object.utils';
import types from 'src/core/service/utils/types.utils';

export default {
    getModuleRoutes,
    registerModule,
    getModuleRegistry,
    getModuleByEntityName
};

/**
 * Registry for modules
 * @type {Map<String, Object>}
 */
const modules = new Map();

/**
 * Returns the registry of all modules mounted in the application.
 *
 * @returns {Map<any, any>} modules - Registry of all modules
 */
function getModuleRegistry() {
    modules.forEach((value, key) => {
        if (hasOwnProperty(value.manifest, 'flag')
            && !Shopware.FeatureConfig.isActive(value.manifest.flag)
        ) {
            modules.delete(key);
        }
    });

    return modules;
}

/**
 * Registers a module in the application. The module will be mounted using
 * the defined routes of the module using the router.
 *
 * @param {String} moduleId - The machine readable name which is used as an identifier for the module
 * @param {Object} module - Module definition - see manifest.js file
 * @returns {Boolean|Object} moduleDefinition - registered module definition
 */
function registerModule(moduleId, module) {
    const type = module.type || 'plugin';
    let moduleRoutes = new Map();

    // A module should always have an unique identifier cause overloading modules can cause unexpected side effects
    if (!moduleId) {
        warn(
            'ModuleFactory',
            'Module has no unique identifier "id". Abort registration.',
            module
        );
        return false;
    }

    if (modules.has(moduleId)) {
        warn(
            'ModuleFactory',
            `A module with the identifier "${moduleId}" is registered already. Abort registration.`,
            modules.get(moduleId)
        );

        return false;
    }

    const splitModuleId = moduleId.split('-');

    if (splitModuleId.length < 2) {
        warn(
            'ModuleFactory',
            'Module identifier does not match the necessary format "[namespace]-[name]":',
            moduleId,
            'Abort registration.'
        );
        return false;
    }

    // Modules will be mounted using the routes definition in the manifest file. If the module doesn't contains a routes
    // definition it isn't accessible in the application.
    if (!hasOwnProperty(module, 'routes')) {
        warn(
            'ModuleFactory',
            `Module "${moduleId}" has no configured routes. The module will not be accessible in the administration UI.`,
            'Abort registration.',
            module
        );
        return false;
    }

    // Sanitize the modules routes
    Object.keys(module.routes).forEach((routeKey) => {
        let route = module.routes[routeKey];

        // Rewrite name and path
        route.name = `${splitModuleId.join('.')}.${routeKey}`;

        // Set the type of the route e.g. "core" or "plugin"
        route.type = type;

        // Core routes don't need to be nested
        if (!route.coreRoute) {
            route.path = `/${splitModuleId.join('/')}/${route.path}`;
        }

        // Generate the component list based on a route
        route = createRouteComponentList(route, moduleId, module);

        if (!route) {
            return;
        }

        // Support for children routes
        if (hasOwnProperty(route, 'children') && Object.keys(route.children).length) {
            route = iterateChildRoutes(route, splitModuleId, routeKey);
            moduleRoutes = registerChildRoutes(route, moduleRoutes);
        }

        // Alias support
        if (route.alias && route.alias.length > 0
            && (!route.coreRoute)) {
            route.alias = `/${splitModuleId.join('/')}/${route.alias}`;
        }

        route.isChildren = false;
        moduleRoutes.set(route.name, route);
    });

    // When we're not having at least one valid route definition we're not registering the module
    if (moduleRoutes.size === 0) {
        warn(
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
    if (hasOwnProperty(module, 'navigation') && module.navigation) {
        if (!types.isArray(module.navigation)) {
            warn(
                'ModuleFactory',
                'The route definition has to be an array.',
                module.navigation
            );
            return false;
        }

        module.navigation = module.navigation.filter((navigationEntry) => {
            if (!navigationEntry.id && !navigationEntry.path && !navigationEntry.parent && !navigationEntry.link) {
                warn(
                    'ModuleFactory',
                    'The navigation entry does not contains the necessary properties',
                    'Abort registration of the navigation entry',
                    navigationEntry
                );
                return false;
            }

            if (!navigationEntry.label || !navigationEntry.label.length) {
                warn(
                    'ModuleFactory',
                    'The navigation entry needs a property called "label"'
                );
                return false;
            }

            return true;
        });
        moduleDefinition.navigation = module.navigation;
    }

    modules.set(moduleId, moduleDefinition);

    return moduleDefinition;
}

/**
 * Registers the route children in the module routes map recursively.
 *
 * @param {Object} routeDefinition
 * @param {Map<String, Object>} moduleRoutes
 * @returns {Map}
 */
function registerChildRoutes(routeDefinition, moduleRoutes) {
    Object.keys(routeDefinition.children).map((key) => {
        const child = routeDefinition.children[key];

        if (hasOwnProperty(child, 'children') && Object.keys(child.children).length) {
            moduleRoutes = registerChildRoutes(child, moduleRoutes);
        }
        moduleRoutes.set(child.name, child);
        return child;
    });

    return moduleRoutes;
}

/**
 * Recursively iterates over the route children definitions and converts the format to the vue-router route definition.
 *
 * @param {Object} routeDefinition
 * @param {Array} moduleName
 * @param {String} parentKey
 * @returns {Object}
 */
function iterateChildRoutes(routeDefinition, moduleName, parentKey) {
    routeDefinition.children = Object.keys(routeDefinition.children).map((key) => {
        let child = routeDefinition.children[key];

        if (child.path && child.path.length === 0) {
            child.path = '';
        } else {
            child.path = `${routeDefinition.path}/${child.path}`;
        }

        child.name = `${moduleName.join('.')}.${parentKey}.${key}`;
        child.isChildren = true;

        if (hasOwnProperty(child, 'children') && Object.keys(child.children).length) {
            child = iterateChildRoutes(child, moduleName, `${parentKey}.${key}`);
        }

        return child;
    });

    return routeDefinition;
}

/**
 * Generates the route component list e.g. adds supports for multiple components per route as well as validating
 * the developer input.
 *
 * @param {Object} route
 * @param {String} moduleId
 * @param {Object} module
 * @returns {void|Object}
 */
function createRouteComponentList(route, moduleId, module) {
    if (hasOwnProperty(module, 'flag')) {
        route.flag = module.flag;
    }

    if (route.components && Object.keys(route.components).length) {
        const componentList = {};

        Object.keys(route.components).forEach((componentKey) => {
            const component = route.components[componentKey];

            // Don't register a component without a name
            if (!component.length || component.length <= 0) {
                warn(
                    'ModuleFactory',
                    `The route definition of module "${moduleId}" is not valid. 
                        A route needs an assigned component name.`
                );
                return;
            }

            componentList[componentKey] = component;
        });

        route.components = componentList;

        return route;
    }

    if (!route.component || !route.component.length) {
        warn(
            'ModuleFactory',
            `The route definition of module "${moduleId}" is not valid. 
                A route needs an assigned component name.`
        );
        return false;
    }

    route.components = {
        default: route.component
    };

    // Remove the component cause we remapped it to the components object of the route object
    delete route.component;

    return route;
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
            if (hasOwnProperty(route, 'flag') && !Shopware.FeatureConfig.isActive(route.flag)) {
                return;
            }

            if (route.isChildren) {
                return;
            }

            moduleRoutes.push(route);
        });
    });

    return moduleRoutes;
}

/**
 * Returns the first found module with the given entity name
 *
 *  @param {String} entityName
 * @returns {undefined|Object}
 */
function getModuleByEntityName(entityName) {
    const filtered = [];
    modules.forEach((module) => {
        if (entityName === module.manifest.entity) {
            filtered.push(module);
        }
    });

    return filtered.shift();
}
