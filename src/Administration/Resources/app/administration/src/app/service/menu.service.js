/**
 * @module app/service/menu
 */
const FlatTree = Shopware.Helper.FlatTreeHelper;

/**
 * @method createMenuService
 * @memberOf module:app/service/menu
 * @param moduleFactory
 * @returns {{getMainMenu: getMainMenu, addItem: FlatTree.add, removeItem: FlatTree.remove}}
 * @constructor
 */
export default function createMenuService(moduleFactory) {
    const flatTree = new FlatTree((first, second) => first.position - second.position);

    return {
        getMainMenu,
        /** @deprecated tag:v6.5.0 will be removed in future version */
        addItem: () => {},
        /** @deprecated tag:v6.5.0 will be removed in future version */
        removeItem: () => {},
        getNavigationFromAdminModules,
        getNavigationFromApps,
    };

    /**
     * Iterates the module registry from the {@link ModuleFactory} and adds the menu items to
     * the flat tree instance.
     *
     * @memberOf module:app/service/menu
     * @deprecated tag:v6.5.0 will be removed. use getAdminNavigation and convert to tree by yourself
     * @returns {Array} main menu as a data tree hierarchy
     */
    function getMainMenu() {
        // Reset tree when not empty
        resetTree();

        getNavigationFromAdminModules().forEach((navigationEntry) => {
            flatTree.add(navigationEntry);
        });

        return flatTree.convertToTree();
    }

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

    /**
     * Reset the flatTree
     *
     * @memberOf module:app/service/menu
     * @deprecated tag:v6.5.0 will be removed with getMainMenu
     * @return {Boolean}
     */
    function resetTree() {
        const flatTreeKeys = [...flatTree._registeredNodes.keys()];
        flatTreeKeys.forEach((node) => {
            flatTree.remove(node);
        });
    }

    function getNavigationFromApps(apps) {
        return apps.reduce((navigation, app) => {
            navigation.push(...getNavigationFromApp(app));
            return navigation;
        }, []);
    }

    function getNavigationFromApp(app) {
        const appLabel = getTranslatedLabel(app.label);

        return app.modules.map((appModule) => {
            const moduleLabel = getTranslatedLabel(appModule.label);

            const entry = {
                id: `app-${app.name}-${appModule.name}`,
                label: {
                    translated: true,
                    label: `${appLabel} - ${moduleLabel}`,
                },
                position: appModule.position,
                parent: getParentFromModule(appModule),
                privilege: `app.${app.name}`,
            };

            if (typeof appModule.position === 'number') {
                entry.position = appModule.position;
            }

            if (appModule.source) {
                entry.path = 'sw.my.apps.index';
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

    /** @deprecated tag:v6.5.0  use module.parent directly when required */
    function getParentFromModule(module) {
        return module.parent || 'sw-my-apps';
    }
}
