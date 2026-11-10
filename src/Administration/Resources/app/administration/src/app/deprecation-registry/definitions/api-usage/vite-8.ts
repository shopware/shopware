/**
 * @sw-package framework
 */

import { apiUsageMigration, objectKey, upgradeGuide } from '../helpers';

export default apiUsageMigration({
    id: 'api.vite-8-build-config',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'Shopware 6.8 builds the Administration with Vite 8 (Rolldown, Oxc, Node ^20.19 or >=22.12). Migrate own Vite configs with the Vite 7 and Vite 8 migration guides.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#administration-build-updated-to-vite-8'),
    ],
    usage: [
        objectKey({
            names: [
                'rollupOptions',
                'esbuildOptions',
                'commonjsOptions',
            ],
            files: '^vite\\.config\\.[cm]?[jt]s$',
            message:
                'Vite 8 bundles with Rolldown: rename rollupOptions to rolldownOptions and optimizeDeps.esbuildOptions to optimizeDeps.rolldownOptions, build.commonjsOptions has no effect.',
        }),
    ],
});
