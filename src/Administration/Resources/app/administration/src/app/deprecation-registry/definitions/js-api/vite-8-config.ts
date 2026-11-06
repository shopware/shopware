/**
 * @sw-package framework
 */

import { configFile, jsApiMigration, objectKey, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.vite-8-config',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The Administration builds with Vite 8 (Rolldown, Oxc, Node ^20.19 or >=22.12). Migrate build configs with the Vite 7 and Vite 8 guides.',
    references: [upgradeGuide('UPGRADE-6.8.md#administration-build-updated-to-vite-8')],
    usage: [
        configFile({ pattern: '^vite\\.config\\.[cm]?[jt]s$' }),
        objectKey({ from: 'rollupOptions', to: 'rolldownOptions' }),
        objectKey({ from: 'esbuildOptions', to: 'optimizeDeps.rolldownOptions' }),
        objectKey({ from: 'commonjsOptions', message: 'build.commonjsOptions has no effect in Vite 8.' }),
    ],
});
