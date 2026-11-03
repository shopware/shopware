import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-category-link-settings',
    load: () => import('./src/sw-category-link-settings'),
    public: {
        props: [
            'category',
            'isLoading',
        ],
        blocks: [
            'sw_category_link_settings',
            'sw_category_detail_link_type_select_main',
            'sw_category_detail_link_type_select_entity',
            'sw_category_detail_link_type_select_entity_category',
            'sw_category_detail_link_type_select_entity_product',
            'sw_category_detail_link_type_select_entity_landing_page',
            'sw_category_detail_link_field',
            'sw_category_detail_link_new_tab',
        ],
        computed: [
            'linkTypeValues',
            'entityValues',
            'mainType',
            'isExternal',
            'isInternal',
            'productCriteria',
            'categoryCriteria',
            'internalLinkCriteria',
            'categoryRepository',
            'categoryLinkPlaceholder',
            'allowedCategoryTypes',
            'categoryLinkHelpText',
        ],
        methods: [
            'createdComponent',
            'changeEntity',
            'createCategoryCollection',
            'onSelectionAdd',
            'onSelectionRemove',
        ],
    },
});
