import utils from 'src/core/service/util.service';

const ModuleFactory = Shopware.ModuleFactory;

export default function MenuService() {
    return {
        getMainMenu
    };
}

function getMainMenu() {
    const modules = ModuleFactory.getModuleRegistry();
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
