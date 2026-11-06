/**
 * @sw-package framework
 */

import { componentMembers, jsApiMigration, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.cms-helpers',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'These CMS helpers are removed.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-cms-helpers')],
    usage: [
        ...componentMembers({
            components: ['sw-category-detail', 'sw-product-detail'],
            members: ['extractSlotOverrides', 'getCmsPageOverrides', 'deleteSpecifcKeys'],
        }),
        ...componentMembers({
            components: ['sw-cms-detail', 'sw-cms-create'],
            members: ['resetCmsPageState'],
            to: 'resetRelatedStores()',
        }),
        ...componentMembers({
            components: ['sw-cms-inherit-wrapper'],
            members: ['isSystemDefaultLanguage'],
            to: "Shopware.Store.get('context').isSystemDefaultLanguage",
        }),
        ...componentMembers({ components: ['sw-cms-section'], members: ['pageSlotconfigError'] }),
        ...componentMembers({ components: ['sw-cms-el-product-slider'], members: ['navArrowsClasses'] }),
        ...componentMembers({
            components: ['sw-cms-list'],
            members: ['associatedCategoryBuckets', 'associatedProductBuckets'],
        }),
        ...componentMembers({
            components: ['sw-cms-layout-modal'],
            members: ['gridPreSelection', 'selectInGrid'],
            to: 'selectItem(page)',
        }),
    ],
});
