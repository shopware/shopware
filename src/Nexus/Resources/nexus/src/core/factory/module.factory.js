import coreModules from 'module';

export default {
    getModuleRoutes
};

function getModuleRoutes() {
    const moduleRoutes = [];

    coreModules.forEach((coreModule) => {
        const moduleId = coreModule.id;

        if (!moduleId) {
            console.warn('[module.factory] Module has no unique identifier', coreModule);
        }

        if (!Object.prototype.hasOwnProperty.call(coreModule, 'routes')) {
            console.warn(`[module.factory] Module "${moduleId}" has no defined routes. The module will not be accessible.`, coreModule);
            return;
        }

        Object.keys(coreModule.routes).forEach((routeKey) => {
            const route = coreModule.routes[routeKey];

            // Rewrite name and path
            route.name = `${moduleId}.${routeKey}`;
            route.path = `/core/${route.path}`;

            route.component = route.component.name;

            // Alias support
            if (route.alias && route.alias.length) {
                route.alias = `/core/${route.alias}`;
            }

            moduleRoutes.push(route);
        });
    });

    return moduleRoutes;
}
