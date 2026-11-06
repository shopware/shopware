/**
 * @sw-package framework
 */

import { componentMember, jsApiMigration, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.cms-helpers',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'These CMS helpers are removed.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-cms-helpers')],
    usage: [
        componentMember({ components: ['sw-category-detail', 'sw-product-detail'], member: 'extractSlotOverrides' }),
        componentMember({ components: ['sw-category-detail', 'sw-product-detail'], member: 'getCmsPageOverrides' }),
        componentMember({ components: ['sw-category-detail', 'sw-product-detail'], member: 'deleteSpecifcKeys' }),
        componentMember({
            components: ['sw-cms-detail', 'sw-cms-create'],
            member: 'resetCmsPageState',
            to: 'resetRelatedStores()',
        }),
        componentMember({
            components: ['sw-cms-inherit-wrapper'],
            member: 'isSystemDefaultLanguage',
            to: "Shopware.Store.get('context').isSystemDefaultLanguage",
        }),
        componentMember({ components: ['sw-cms-section'], member: 'pageSlotconfigError' }),
        componentMember({ components: ['sw-cms-el-product-slider'], member: 'navArrowsClasses' }),
        componentMember({ components: ['sw-cms-list'], member: 'associatedCategoryBuckets' }),
        componentMember({ components: ['sw-cms-list'], member: 'associatedProductBuckets' }),
        componentMember({ components: ['sw-cms-layout-modal'], member: 'gridPreSelection', to: 'selectItem(page)' }),
        componentMember({ components: ['sw-cms-layout-modal'], member: 'selectInGrid', to: 'selectItem(page)' }),
    ],
});
