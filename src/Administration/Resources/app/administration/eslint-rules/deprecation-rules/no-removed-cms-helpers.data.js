/**
 * @sw-package framework
 *
 * Texts of the `cms-helpers` codemod: component methods for CMS page overrides that 6.8 removes. Taken from the
 * `@deprecated tag:v6.8.0` markers in `sw-cms-detail`, `sw-category-detail` and `sw-product-detail`.
 *
 * `extractSlotOverrides`, `getCmsPageOverrides` and `deleteSpecifcKeys` (sic) exist nowhere else in the core and are
 * matched everywhere. `resetCmsPageState` is also an action of the Pinia store `cmsPage`, which stays.
 */

const FORM_SYNC = 'the layout editor now writes slot config changes back to the entity itself';

module.exports = {
    id: 'cms-helpers',
    description: 'Marks the CMS page override helpers that 6.8 removes from sw-cms-detail, sw-category-detail and sw-product-detail',
    members: [
        {
            component: ['sw-cms-detail', 'sw-cms-create'],
            name: 'resetCmsPageState',
            message:
                'sw-cms-detail.resetCmsPageState() is removed. Override or call resetRelatedStores() instead; it resets ' +
                'the cmsPage, swCategoryDetail and swProductDetail stores.',
        },
        {
            name: 'extractSlotOverrides',
            unique: true,
            message: `sw-category-detail.extractSlotOverrides() is removed without replacement; ${FORM_SYNC}.`,
        },
        {
            name: 'getCmsPageOverrides',
            unique: true,
            message: `getCmsPageOverrides() of sw-category-detail and sw-product-detail is removed without replacement; ${FORM_SYNC}.`,
        },
        {
            name: 'deleteSpecifcKeys',
            unique: true,
            message: `deleteSpecifcKeys() of sw-category-detail and sw-product-detail is removed without replacement; ${FORM_SYNC}.`,
        },
    ],
};
