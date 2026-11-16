/**
 * @sw-package framework
 */

import { componentMigration, renameComponent, tabItemsToItemsProp, upgradeGuide } from '../helpers';

export default componentMigration({
    id: 'component.sw-tabs-conversion',
    component: 'sw-tabs',
    replacement: 'mt-tabs',
    deprecatedIn: '6.7.0',
    removedIn: '6.9.0',
    description: 'sw-tabs with sw-tabs-item children becomes mt-tabs with an items array.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#replace-sw-tabs-with-mt-tabs'),
    ],
    onConvert: [
        renameComponent({ from: 'sw-tabs', to: 'mt-tabs' }),
        tabItemsToItemsProp(),
    ],
});
