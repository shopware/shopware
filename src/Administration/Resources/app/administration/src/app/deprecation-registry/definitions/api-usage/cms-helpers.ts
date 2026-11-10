/**
 * @sw-package framework
 */

import { apiUsageMigration, componentMember, upgradeGuide } from '../helpers';

const REMOVED = 'It is removed in 6.8 without replacement.';

export default apiUsageMigration({
    id: 'api.cms-helpers',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Several CMS helpers of the category, product and layout pages were removed.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-cms-helpers'),
    ],
    usage: [
        componentMember({
            components: ['sw-category-detail'],
            names: [
                'extractSlotOverrides',
                'getCmsPageOverrides',
                'deleteSpecifcKeys',
            ],
            message: REMOVED,
        }),
        componentMember({
            components: ['sw-product-detail'],
            names: [
                'getCmsPageOverrides',
                'deleteSpecifcKeys',
            ],
            message: REMOVED,
        }),
        componentMember({
            components: [
                'sw-cms-detail',
                'sw-cms-create',
            ],
            names: ['resetCmsPageState'],
            message: 'It is removed in 6.8. Use resetRelatedStores().',
        }),
        componentMember({
            components: ['sw-cms-inherit-wrapper'],
            names: ['isSystemDefaultLanguage'],
            message: "It is removed in 6.8. Use Shopware.Store.get('context').isSystemDefaultLanguage.",
        }),
        componentMember({ components: ['sw-cms-section'], names: ['pageSlotconfigError'], message: REMOVED }),
        componentMember({ components: ['sw-cms-el-product-slider'], names: ['navArrowsClasses'], message: REMOVED }),
        componentMember({
            components: ['sw-cms-list'],
            names: [
                'associatedCategoryBuckets',
                'associatedProductBuckets',
            ],
            message: REMOVED,
        }),
        componentMember({
            components: ['sw-cms-layout-modal'],
            names: [
                'gridPreSelection',
                'selectInGrid',
            ],
            message: 'It is removed in 6.8. Use selectItem(page) or onSelectPage(pageId).',
        }),
    ],
});
