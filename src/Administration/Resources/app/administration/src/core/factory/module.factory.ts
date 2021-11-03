/**
 * @module core/factory/module
 */
import { warn } from 'src/core/service/utils/debug.utils';
import { hasOwnProperty, merge } from 'src/core/service/utils/object.utils';
import types from 'src/core/service/utils/types.utils';
import MiddlewareHelper from 'src/core/helper/middleware.helper';
import type { Route, RouteConfig } from 'vue-router';
import { ComponentConfig } from './component.factory';

export default {
    getModuleRoutes,
    registerModule,
    getModuleRegistry,
    getModuleByEntityName,
    getModuleSnippets,
};

export type ModuleTypes = 'plugin' | 'core';
type ModuleRoutes = Map<string, RouteConfig>

interface Navigation {
    moduleType: ModuleTypes,
    parent?: string,
    id: string,
    path?: string,
    link?: string,
    label?: string,
    position?: number,
}

interface SettingsItem {
    group: 'shop' | 'system' | 'plugin',
    to: string,
    icon?: string,
    iconComponent: unknown,
    privilege?: string,
    id?: string,
    name?: string,
    label?: string,
}

interface ModuleManifest {
    flag: string,
    type: ModuleTypes,
    routeMiddleware: (next: () => void, currentRoute: Route) => void,
    routes: {
        [key: string]: RouteConfig
    },
    routePrefixName: string,
    routePrefixPath: string,
    coreRoute?: boolean,
    navigation?: Navigation[],
    settingsItem?: SettingsItem[] | SettingsItem,
    extensionEntryRoute?: {
        extensionName: string,
        route: string,
    },
    entity?: string,
    snippets?: {
        [lang: string]: unknown
    },
    name: string,
    title: string
}

interface ModuleDefinition {
    manifest: ModuleManifest,
    navigation?: Navigation[],
    routes: ModuleRoutes,
    type: ModuleTypes
}

/**
 * Registry for modules
 * @type {Map<String, Object>}
 */
const modules: Map<string, ModuleDefinition> = new Map();

const middlewareHelper = new MiddlewareHelper();

/**
 * Returns the registry of all modules mounted in the application.
 */
function getModuleRegistry(): Map<string, ModuleDefinition> {
    modules.forEach((value, key) => {
        if (hasOwnProperty(value.manifest, 'flag')
            && !Shopware.Feature.isActive(value.manifest.flag)
        ) {
            modules.delete(key);
        }
    });

    return modules;
}

/**
 * Registers a module in the application. The module will be mounted using
 * the defined routes of the module using the router.
 */
function registerModule(moduleId: string, module: ModuleManifest): false | ModuleDefinition {
    const type = module.type || 'plugin';
    let moduleRoutes: ModuleRoutes = new Map();

    // A module should always have an unique identifier cause overloading modules can cause unexpected side effects
    if (!moduleId) {
        warn(
            'ModuleFactory',
            'Module has no unique identifier "id". Abort registration.',
            module,
        );
        return false;
    }

    if (modules.has(moduleId)) {
        warn(
            'ModuleFactory',
            `A module with the identifier "${moduleId}" is registered already. Abort registration.`,
            modules.get(moduleId),
        );

        return false;
    }

    const splitModuleId = moduleId.split('-');

    if (splitModuleId.length < 2) {
        warn(
            'ModuleFactory',
            'Module identifier does not match the necessary format "[namespace]-[name]":',
            moduleId,
            'Abort registration.',
        );
        return false;
    }

    // Modules will be mounted using the routes definition in the manifest file. If the module doesn't contains a routes
    // definition it isn't accessible in the application.
    if (!hasOwnProperty(module, 'routes') && !(module.routeMiddleware)) {
        warn(
            'ModuleFactory',
            `Module "${moduleId}" has no configured routes or a routeMiddleware.`,
            'The module will not be accessible in the administration UI.',
            'Abort registration.',
            module,
        );
        return false;
    }

    // Sanitize the modules routes
    if (hasOwnProperty(module, 'routes')) {
        Object.keys(module.routes).forEach((routeKey) => {
            let route = module.routes[routeKey];

            // Check if custom prefix name exists
            const routePrefixName = module.routePrefixName ? module.routePrefixName : splitModuleId.join('.');

            // Rewrite name
            route.name = `${routePrefixName}.${routeKey}`;

            // Check if custom prefix path exists
            const routePrefixPath = module.routePrefixPath ? module.routePrefixPath : splitModuleId.join('/');

            // Core routes don't need to be nested
            if (!route.coreRoute) {
                // Rewrite path
                route.path = `/${routePrefixPath}/${route.path}`;
            }


            // Set the type of the route e.g. "core" or "plugin"
            route.type = type;

            // Generate the component list based on a route
            route = createRouteComponentList(route, moduleId, module);

            if (!route) {
                return;
            }

            // Support for children routes
            const childrenRoutes = route.children ?? {};
            if (hasOwnProperty(route, 'children') && Object.keys(childrenRoutes).length) {
                route = iterateChildRoutes(route);

                moduleRoutes = registerChildRoutes(route, moduleRoutes);
            }

            // Alias support
            if (
                route.alias
                && typeof route.alias === 'string'
                && route.alias.length > 0
                && (!route.coreRoute)
            ) {
                route.alias = `/${splitModuleId.join('/')}/${route.alias}`;
            }

            route.isChildren = false;
            route.routeKey = routeKey;

            moduleRoutes.set(route.name ?? '', route);
        });
    }

    // We only register the module if it either has one valid route or uses the router middleware
    if (module.routeMiddleware && types.isFunction(module.routeMiddleware)) {
        middlewareHelper.use(module.routeMiddleware);
    } else if (moduleRoutes.size === 0) {
        warn(
            'ModuleFactory',
            `The module "${moduleId}" was not registered cause it hasn't a valid route definition`,
            'Abort registration.',
            module.routes,
        );
        return false;
    }

    const moduleDefinition: ModuleDefinition = {
        routes: moduleRoutes,
        manifest: module,
        type,
    };

    // Add the navigation of the module to the module definition. We'll create a menu entry later on
    if (hasOwnProperty(module, 'navigation') && module.navigation) {
        if (!types.isArray(module.navigation)) {
            warn(
                'ModuleFactory',
                'The route definition has to be an array.',
                module.navigation,
            );
            return false;
        }

        module.navigation = module.navigation.filter((navigationEntry) => {
            navigationEntry.moduleType = module.type;

            if (module.type === 'plugin' && !navigationEntry.parent) {
                warn(
                    'ModuleFactory',
                    'Navigation entries from plugins are not allowed on the first level.',
                    'Set a property "parent" to register your navigation entry',
                );
                return false;
            }

            if (!navigationEntry.id && !navigationEntry.path && !navigationEntry.parent && !navigationEntry.link) {
                warn(
                    'ModuleFactory',
                    'The navigation entry does not contains the necessary properties',
                    'Abort registration of the navigation entry',
                    navigationEntry,
                );
                return false;
            }

            if (!navigationEntry.label || !navigationEntry.label.length) {
                warn(
                    'ModuleFactory',
                    'The navigation entry needs a property called "label"',
                );
                return false;
            }

            if (module.type === 'plugin') {
                if (navigationEntry.position) {
                    navigationEntry.position += 1000;
                } else {
                    navigationEntry.position = 1000;
                }
            }

            return true;
        });
        moduleDefinition.navigation = module.navigation;
    }

    if (hasOwnProperty(module, 'settingsItem') && module.settingsItem) {
        addSettingsItemsToStore(moduleId, module);
    }

    if (hasOwnProperty(module, 'extensionEntryRoute') && module.extensionEntryRoute) {
        addEntryRouteToExtensionRouteStore(module.extensionEntryRoute);
    }

    modules.set(moduleId, moduleDefinition);

    return moduleDefinition;
}

/**
 * Registers the route children in the module routes map recursively.
 */
function registerChildRoutes(routeDefinition: RouteConfig, moduleRoutes: ModuleRoutes): ModuleRoutes {
    Object.values(routeDefinition.children ?? {}).map((child) => {
        if (hasOwnProperty(child, 'children') && Object.keys(child.children ?? {}).length) {
            moduleRoutes = registerChildRoutes(child, moduleRoutes);
        }
        moduleRoutes.set(child.name ?? '', child);
        return child;
    });

    return moduleRoutes;
}

/**
 * Recursively iterates over the route children definitions and converts the format to the vue-router route definition.
 */
function iterateChildRoutes(routeDefinition: RouteConfig): RouteConfig {
    const routeDefinitionChildren = routeDefinition.children;

    if (!routeDefinitionChildren || routeDefinitionChildren === undefined) {
        return routeDefinition;
    }

    routeDefinition.children = Object.entries(routeDefinitionChildren).map(([key, child]) => {
        if (child.path && child.path.length === 0) {
            child.path = '';
        } else {
            child.path = `${routeDefinition.path}/${child.path}`;
        }

        child.name = `${routeDefinition.name ?? ''}.${key}`;
        child.isChildren = true;

        if (hasOwnProperty(child, 'children') && Object.keys(child.children ?? {}).length) {
            child = iterateChildRoutes(child);
        }

        return child;
    });

    return routeDefinition;
}

/**
 * Generates the route component list e.g. adds supports for multiple components per route as well as validating
 * the developer input.
 */
function createRouteComponentList(route: RouteConfig, moduleId: string, module: ModuleManifest): RouteConfig {
    if (hasOwnProperty(module, 'flag')) {
        route.flag = module.flag;
    }

    // Remove the component cause we remapped it to the components object of the route object
    if (route.component) {
        route.components = {
            default: route.component,
        };
        delete route.component;
    }

    const componentList: { [componentKey: string]: ComponentConfig } = {};
    const routeComponents = route.components ?? {};
    Object.keys(routeComponents).forEach((componentKey) => {
        const component = routeComponents[componentKey];

        // Don't register a component without a name
        if (!component) {
            warn(
                'ModuleFactory',
                `The route definition of module "${moduleId}" is not valid.
                    A route needs an assigned component name.`,
            );
            return;
        }

        // @ts-expect-error
        componentList[componentKey] = component;
    });

    // @ts-expect-error
    route.components = componentList;

    return route;
}

/**
 * Returns the defined module routes which will be registered in the router and therefore will be accessible in the
 * application.
 */
function getModuleRoutes(): RouteConfig[] {
    const moduleRoutes: RouteConfig[] = [];

    modules.forEach((module) => {
        module.routes.forEach((route) => {
            if (hasOwnProperty(route, 'flag') && !Shopware.Feature.isActive(route.flag)) {
                return;
            }

            if (route.isChildren) {
                return;
            }
            middlewareHelper.go(route);
            moduleRoutes.push(route);
        });
    });

    return moduleRoutes;
}

/**
 * Returns the first found module with the given entity name
 */
function getModuleByEntityName(entityName: string): ModuleDefinition | undefined {
    return Array.from(modules.values()).find((value) => {
        return entityName === value.manifest.entity;
    });
}

/**
 * Returns a list of all module specific snippets
 */
function getModuleSnippets(): { [lang:string]: unknown } {
    return Array.from(modules.values()).reduce<{ [lang:string] : unknown }>((accumulator, module) => {
        const manifest = module.manifest;

        if (!hasOwnProperty(manifest, 'snippets')) {
            return accumulator;
        }

        const localeKey = Object.keys(manifest.snippets ?? {});
        if (!localeKey.length) {
            return accumulator;
        }

        localeKey.forEach((key) => {
            if (!hasOwnProperty(accumulator, key)) {
                accumulator[key] = {};
            }
            if (manifest.snippets) {
                const snippets = manifest.snippets[key];
                accumulator[key] = merge(accumulator[key], snippets);
            }
        });

        return accumulator;
    }, {});
}

/**
 * Adds a module to the settingsItems Store
 */
function addSettingsItemsToStore(moduleId: string, module: ModuleManifest): void {
    if (hasOwnProperty(module, 'flag') && !Shopware.Feature.isActive(module.flag)) {
        return;
    }

    if (!module.settingsItem) {
        return;
    }

    if (!Array.isArray(module.settingsItem)) {
        module.settingsItem = [module.settingsItem];
    }

    module.settingsItem.forEach((settingsItem) => {
        if (settingsItem.group
            && settingsItem.to
            && (settingsItem.icon || settingsItem.iconComponent)
        ) {
            if (!hasOwnProperty(settingsItem, 'id') || !settingsItem.id) {
                settingsItem.id = moduleId;
            }

            if (!hasOwnProperty(settingsItem, 'name') || !settingsItem.name) {
                settingsItem.name = module.name;
            }

            if (!hasOwnProperty(settingsItem, 'label') || !settingsItem.label) {
                settingsItem.label = module.title;
            }

            Shopware.State.commit('settingsItems/addItem', settingsItem);
        } else {
            warn(
                'ModuleFactory',
                'The settingsItem entry does not contain the necessary properties',
                'Abort registration of settingsItem entry',
                settingsItem,
            );
        }
    });
}

function addEntryRouteToExtensionRouteStore(config: { extensionName: string, route: string }):void {
    if (config.extensionName === 'string') {
        warn(
            'ModuleFactory',
            'extensionEntryRoute.extensionName needs to be an string',
        );

        return;
    }

    if (config.route === 'string') {
        warn(
            'ModuleFactory',
            'extensionEntryRoute.route needs to be an string',
        );

        return;
    }

    Shopware.State.commit('extensionEntryRoutes/addItem', config);
}
