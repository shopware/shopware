/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 * @module app/service/menu
 * @method createMenuService
 * @memberOf module:app/service/menu
 * @param moduleFactory
 * @returns {{getMainMenu: getMainMenu, addItem: FlatTree.add, removeItem: FlatTree.remove}}
 * @constructor
 */
export default function createMenuService(moduleFactory) {
    return {
        getNavigationFromAdminModules,
        getNavigationFromApps,
    };

    /**
     * Iterates the module registry from the {@link ModuleFactory} and returns all navigation entries as a flat array
     *
     * @memberOf module:app/service/menu
     * @returns {Array} Navigation entries of all registered admin modules
     */
    function getNavigationFromAdminModules() {
        const modules = moduleFactory.getModuleRegistry();
        const navigationEntries = [];

        modules.forEach((module) => {
            const moduleNavigation = Array.isArray(module.navigation) ? module.navigation : [];

            navigationEntries.push(...moduleNavigation);
        });

        return navigationEntries;
    }

    function getNavigationFromApps(apps) {
        return apps.reduce((navigation, app) => {
            navigation.push(...getNavigationFromApp(app));
            return navigation;
        }, []);
    }

    function getNavigationFromApp(app) {
        return app.modules.map((appModule) => {
            const moduleLabel = getTranslatedLabel(appModule.label);

            const entry = {
                id: `app-${app.name}-${appModule.name}`,
                label: {
                    translated: true,
                    label: moduleLabel,
                },
                position: appModule.position,
                parent: appModule.parent,
                privilege: `app.${app.name}`,
            };

            if (typeof appModule.position === 'number') {
                entry.position = appModule.position;
            }

            if (appModule.source) {
                entry.path = 'sw.extension.module';
                entry.params = { appName: app.name, moduleName: appModule.name };
            }

            return entry;
        });
    }

    function getTranslatedLabel(label) {
        const locale = Shopware.State.get('session').currentLocale;
        const fallbackLocale = Shopware.Context.app.fallbackLocale;

        return label[locale] || label[fallbackLocale];
    }
}
