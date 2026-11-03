import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package inventory
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-product-category-form',
    load: () => import('./src/sw-product-category-form'),
    public: {
        props: [
            'allowEdit',
        ],
        blocks: [
            'sw_product_category_form',
            'sw_product_category_form_visibility_title',
            'sw_product_category_form_visibility_body',
            'sw_product_category_form_visibility_field',
            'sw_product_category_form_visibility_field_link',
            'sw_product_category_form_active_field',
            'sw_product_category_form_category_field',
            'sw_product_category_form_categories_title',
            'sw_product_category_form_categories_body',
            'sw_product_category_form_tags_field',
            'sw_product_category_form_search_keyword_field',
        ],
        computed: [
            'product',
            'parentProduct',
            'loading',
            'isChild',
            'showModeSetting',
            'hasSelectedVisibilities',
            'productVisibilityRepository',
            'salesChannelRepository',
        ],
        methods: [
            'createdComponent',
            'displayAdvancedVisibility',
            'closeAdvancedVisibility',
            'visibilitiesRemoveInheritanceFunction',
        ],
    },
});
