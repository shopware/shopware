declare global {
    interface Window {
        // eslint-disable-next-line @typescript-eslint/consistent-type-imports
        DIVEClass: typeof import('@shopware-ag/dive').DIVE;
        // eslint-disable-next-line @typescript-eslint/consistent-type-imports
        ARSystem: import('@shopware-ag/dive/modules/ARSystem').ARSystem;
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

    if (window.ARSystem) {
        return Promise.resolve();
    }

    if (!window.loadDiveUtil.promise) {
        window.loadDiveUtil.promise = new Promise((resolve) => {
            const diveModule = import('@shopware-ag/dive');
            const stateModule = import('@shopware-ag/dive/modules/State');
            const arSystemModule = import('@shopware-ag/dive/modules/ARSystem');

            // eslint-disable-next-line @typescript-eslint/no-floating-promises
            Promise.all([diveModule, arSystemModule, stateModule]).then(([diveModule, arSystemModule]) => {
                window.DIVEClass = diveModule.DIVE;
                window.ARSystem = new arSystemModule.ARSystem();
                resolve();
            });
        });
    }

    return window.loadDiveUtil.promise;
}
