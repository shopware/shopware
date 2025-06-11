declare global {
    interface Window {
        // eslint-disable-next-line @typescript-eslint/consistent-type-imports
        DIVEClass: typeof import('@shopware-ag/dive').DIVE;
        // eslint-disable-next-line @typescript-eslint/consistent-type-imports
        DIVEARPlugin: typeof import('@shopware-ag/dive/ar');
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

    if (!window.loadDiveUtil.promise) {
        window.loadDiveUtil.promise = new Promise((resolve) => {
            const diveModule = import('@shopware-ag/dive');
            const statePlugin = import('@shopware-ag/dive/state');
            const arPlugin = import('@shopware-ag/dive/ar');

            // eslint-disable-next-line @typescript-eslint/no-floating-promises
            Promise.all([diveModule, arPlugin, statePlugin]).then(([diveModule, arPlugin]) => {
                window.DIVEClass = diveModule.DIVE;
                window.DIVEARPlugin = arPlugin;
                resolve();
            });
        });
    }

    return window.loadDiveUtil.promise;
}
