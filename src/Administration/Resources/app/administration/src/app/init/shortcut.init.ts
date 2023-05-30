// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeShortcutService() {
    const factoryContainer = Shopware.Application.getContainer('factory');
    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
    const shortcutFactory = factoryContainer.shortcut;
    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
    const shortcutService = Shopware.Service('shortcutService');
    const loginService = Shopware.Service('loginService');

    // Register default Shortcuts
    const defaultShortcuts = defaultShortcutMap();
    defaultShortcuts.forEach((sc) => {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
        shortcutFactory.register(sc.combination, sc.path);
    });

    // Initializes the global event listener
    if (loginService.isLoggedIn()) {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
        shortcutService.startEventListener();
    } else {
        loginService.addOnTokenChangedListener(() => {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            shortcutService.startEventListener();
        });
    }

    // Release global event listener on logout
    loginService.addOnLogoutListener(() => {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
        shortcutService.stopEventListener();
    });

    // eslint-disable-next-line @typescript-eslint/no-unsafe-return
    return shortcutFactory;
}

function defaultShortcutMap() {
    return [
        // Add an entity
        { combination: 'AP', path: '/sw/product/create/base' },
        { combination: 'AC', path: '/sw/category/index' },
        { combination: 'AU', path: '/sw/customer/create' },
        { combination: 'APR', path: '/sw/property/create' },
        { combination: 'AM', path: '/sw/manufacturer/create' },
        { combination: 'AR', path: '/sw/settings/rule/create' },

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
        { combination: 'GPO', path: '/sw/promotion/v2/index' },
        { combination: 'GN', path: '/sw/newsletter/recipient/index' },
        { combination: 'GS', path: '/sw/settings/index' },
        { combination: 'GSN', path: '/sw/settings/snippet/index' },
        { combination: 'GSP', path: '/sw/settings/payment/index' },
        { combination: 'GSS', path: '/sw/settings/shipping/index' },
        { combination: 'GSR', path: '/sw/settings/rule/index' },
        { combination: 'GA', path: '/sw/extension/my-extensions/listing/app' },
    ];
}
