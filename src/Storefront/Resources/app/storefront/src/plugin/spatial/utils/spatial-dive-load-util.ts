declare global {
    interface Window {
        // eslint-disable-next-line @typescript-eslint/consistent-type-imports
        DIVEClass: typeof import('@shopware-ag/dive').DIVE;
        // eslint-disable-next-line @typescript-eslint/consistent-type-imports
        DIVEARPlugin: typeof import('@shopware-ag/dive/ar');
        // eslint-disable-next-line @typescript-eslint/consistent-type-imports
        DIVEQuickViewPlugin: typeof import('@shopware-ag/dive/quickview');
        loadDiveUtil: {
            promise: Promise<void> | null;
        };
    }
}

/**
 * @package innovation
 *
 * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
 */
export async function loadDIVE(): Promise<void> {
    if (!window.loadDiveUtil) {
        window.loadDiveUtil = {
            promise: null,
        };
    }

    if (window.DIVEClass) {
        return Promise.resolve();
    }

    if (window.DIVEARPlugin) {
        return Promise.resolve();
    }

    if (window.DIVEQuickViewPlugin) {
        return Promise.resolve();
    }

    if (!window.loadDiveUtil.promise) {
        window.loadDiveUtil.promise = new Promise((resolve) => {
            const diveModule = import('@shopware-ag/dive');
            const arPlugin = import('@shopware-ag/dive/ar');
            const quickViewPlugin = import('@shopware-ag/dive/quickview');

            // eslint-disable-next-line @typescript-eslint/no-floating-promises
            Promise.all([diveModule, arPlugin, quickViewPlugin]).then(([diveModule, arPlugin, quickViewPlugin]) => {
                window.DIVEClass = diveModule.DIVE;
                window.DIVEARPlugin = arPlugin;
                window.DIVEQuickViewPlugin = quickViewPlugin;
                resolve();
            });
        });
    }


    return window.loadDiveUtil.promise;
}
