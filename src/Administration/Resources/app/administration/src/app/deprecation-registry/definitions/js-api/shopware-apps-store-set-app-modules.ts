/**
 * @sw-package framework
 */

import { jsApiMigration, memberCall, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.shopware-apps-store.set-app-modules',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The setAppModules action in the shopwareApps store is deprecated and should not be used by custom Administration code.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#administration'),
    ],
    usage: [
        memberCall({ from: "Shopware.Store.get('shopwareApps').setAppModules", fix: 'manual' }),
    ],
});
