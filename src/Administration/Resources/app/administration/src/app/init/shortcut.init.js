export default function initializeShortcutService() {
    const factoryContainer = this.getContainer('factory');
    const shortcutFactory = factoryContainer.shortcut;
    const shortcutService = Shopware.Service('shortcutService');
    const loginService = Shopware.Service('loginService');

    // Register default Shortcuts
    const defaultShortcuts = defaultShortcutMap();
    defaultShortcuts.forEach((sc) => {
        shortcutFactory.register(sc.combination, sc.path);
    });

    // Initializes the global event listener
    if (loginService.isLoggedIn()) {
        shortcutService.startEventListener();
    } else {
        loginService.addOnTokenChangedListener(() => {
            shortcutService.startEventListener();
        });
    }

    // Release global event listener on logout
    loginService.addOnLogoutListener(() => {
        shortcutService.stopEventListener();
    });

    return shortcutFactory;
}

function defaultShortcutMap() {
    return [
        // Add an entity
        { combination: 'AP', path: '/sw/product/create/base' },
        { combination: 'AC', path: '/sw/category/index' },
        { combination: 'AE', path: '/sw/cms/create' },
        { combination: 'AU', path: '/sw/customer/create/base' },
        { combination: 'APR', path: '/sw/property/create' },
        { combination: 'AM', path: '/sw/manufacturer/create' },
        { combination: 'AR', path: '/sw/settings/rule/create' },
        { combination: 'AS', path: '/sw/product/stream/create' },

        // Go to ...
        { combination: 'GH', path: '/sw/dashboard/index' },
        { combination: 'GP', path: '/sw/product/index' },
        { combination: 'GC', path: '/sw/category/index' },
        { combination: 'GD', path: '/sw/product/stream/index' },
        { combination: 'GPR', path: '/sw/property/index' },
        { combination: 'GM', path: '/sw/manufacturer/index' },
        { combination: 'GO', path: '/sw/order/index' },
        { combination: 'GU', path: '/sw/customer/index' },
        { combination: 'GE', path: '/sw/cms/index' },
        { combination: 'GME', path: '/sw/media/index' },
        { combination: 'GPO', path: '/sw/promotion/index' },
        { combination: 'GN', path: '/sw/newsletter/recipient/index' },
        { combination: 'GS', path: '/sw/settings/index' },
        { combination: 'GSN', path: '/sw/settings/snippet/index' },
        { combination: 'GSP', path: '/sw/settings/payment/index' },
        { combination: 'GSS', path: '/sw/settings/shipping/index' },
        { combination: 'GSR', path: '/sw/settings/rule/index' },
        { combination: 'GA', path: '/sw/extension/my-extensions/listing/app' },
    ];
}
