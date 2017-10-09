import utils from 'src/core/service/util.service';

export default {
    getMainMenu
};

function getMainMenu() {
    const modules = Shopware.ModuleFactory.getModuleRegistry();
    const menuEntries = {};

    modules.forEach(module => {
        if (!Object.prototype.hasOwnProperty.bind(module, 'navigation') || !module.navigation) {
            return;
        }

        Object.keys(module.navigation).forEach((navigationKey) => {
            const menuEntry = module.navigation[navigationKey];
            utils.merge(menuEntries, { [navigationKey]: menuEntry });
        });
    });

    return menuEntries.root[0];
}
