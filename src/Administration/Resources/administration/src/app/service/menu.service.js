/**
 * @module app/service/menu
 */
import FlatTree from 'src/core/helper/flattree.helper';

/**
 * @method createMenuService
 * @memberOf module:app/service/menu
 * @param moduleFactory
 * @returns {{getMainMenu: getMainMenu, addItem: FlatTree.add, removeItem: FlatTree.remove}}
 * @constructor
 */
export default function createMenuService(moduleFactory) {
    const flatTree = new FlatTree();

    return {
        getMainMenu,
        addItem: flatTree.add,
        removeItem: flatTree.remove
    };

    /**
     * Iterates the module registry from the {@link ModuleFactory} and adds the menu items to
     * the flat tree instance.
     *
     * @memberOf module:app/service/menu
     * @returns {Object} main menu as a data tree hierarchy
     */
    function getMainMenu() {
        const modules = moduleFactory.getModuleRegistry();

        modules.forEach((module) => {
            if (!Object.prototype.hasOwnProperty.bind(module, 'navigation') || !module.navigation) {
                return;
            }

            module.navigation.forEach((navigationElement) => {
                flatTree.add(navigationElement);
            });
        });

        return flatTree.convertToTree();
    }
}
