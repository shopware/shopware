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
    name: 'sw-product-restriction-selection',
    load: () => import('./src/sw-product-restriction-selection'),
    public: {
        props: [
            'groupsWithOptions',
            'restriction',
        ],
        emits: [
            'restriction-delete',
        ],
        slots: [
            'contentAfter',
        ],
        blocks: [
            'sw_product_restriction_selection',
            'sw_product_restriction_selection_container',
            'sw_product_restriction_selection_select_group_wrapper',
            'sw_product_restriction_selection_select_group',
            'sw_product_restriction_selection_is_from',
            'sw_product_restriction_selection_select_option_wrapper',
            'sw_product_restriction_selection_multi_select',
            'sw_product_restriction_selection_context_button',
            'sw_product_restriction_selection_context_button_items',
            'sw_product_restriction_selection_content_after',
        ],
        computed: [
            'availableGroups',
            'availableGroupsOptions',
        ],
        methods: [
            'mountedComponent',
            'deleteRestriction',
        ],
    },
});
