import baseModules from 'module';
import types from 'src/core/service/utils/types.utils';
import { hasOwnProperty } from 'src/core/service/utils/object.utils';

export default function initializeCoreModules() {
    const factoryContainer = this.getContainer('factory');
    const moduleFactory = factoryContainer.module;
    const componentFactory = factoryContainer.component;

    // Register modules
    baseModules.forEach((module) => {
        if (module === undefined) {
            return;
        }
        moduleFactory.registerModule(module.name, module);

        // Check if the module has overrides e.g. settings for example
        if (hasOwnProperty(module, 'overrides')) {
            module.overrides.forEach((override) => {
                registerComponent(componentFactory, override);
            });
        }

        // Check if the module has components
        if (hasOwnProperty(module, 'components')) {
            module.components.forEach((component) => {
                registerComponent(componentFactory, component);
            });
        }
    });

    const routes = moduleFactory.getModuleRoutes();
    return routes.map((route) => {
        // Registering route components
        route.components = Object.keys(route.components).reduce((accumulator, componentKey) => {
            const component = route.components[componentKey];

            accumulator[componentKey] = registerComponent(componentFactory, component);
            return accumulator;
        }, {});

        // Support for child routes
        if (route.children && route.children.length > 0) {
            route.children = route.children.map((child) => {
                const component = child.component;
                child.component = registerComponent(componentFactory, component);
                return child;
            });
        }

        return route;
    });
}

/**
 * Registers a component definition into the application.
 *
 * @param {Object} componentFactory
 * @param {Object|String} component
 * @returns {String}
 */
function registerComponent(componentFactory, component) {
    if (types.isString(component)) {
        return component;
    }

    const isExtendedComponent = (component.extendsFrom && component.extendsFrom.length);
    const isOverrideComponent = (component.overrideFrom && component.overrideFrom.length);

    if (isExtendedComponent) {
        componentFactory.extend(component.name, component.extendsFrom, component);
    } else if (isOverrideComponent) {
        componentFactory.override(component.overrideFrom, component);
    } else {
        componentFactory.register(component.name, component);
    }

    return component.name;
}
