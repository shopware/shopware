import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-promotion-v2-list',
    load: () => import('./src/sw-promotion-v2-list'),
    public: {
        blocks: [
            'sw_promotion_v2_list',
            'sw_promotion_v2_search_bar',
            'sw_promotion_v2_list_smart_bar_header',
            'sw_promotion_v2_list_smart_bar_header_title',
            'sw_promotion_v2_list_smart_bar_header_title_text',
            'sw_promotion_v2_list_smart_bar_header_amount',
            'sw_promotion_v2_list_smart_bar_actions',
            'sw_promotion_v2_list_smart_bar_actions_add',
            'sw_promotion_v2_list_language_switch',
            'sw_promotion_v2_list_content',
            'sw_promotion_v2_list_empty_state',
            'sw_promotion_v2_list_empty_state_actions',
            'sw_promotion_v2_list_grid',
            'sw_promotion_v2_list_grid_columns',
            'sw_promotion_v2_list_grid_columns_active',
            'sw_promotion_v2_list_grid_columns_valid_from',
            'sw_promotion_v2_list_grid_columns_valid_until',
            'sw_promotion_v2_list_grid_columns_actions',
            'sw_promotion_v2_list_grid_columns_actions_duplicate',
            'sw_promotion_v2_list_grid_columns_actions_delete',
            'sw_promotion_v2_list_sidebar',
            'sw_promotion_v2_list_sidebar_refresh',
        ],
        computed: [
            'promotionRepository',
            'promotionCriteria',
            'promotionColumns',
            'addButtonTooltip',
            'dateFilter',
            'allowBulkDelete',
            'adminEsEnable',
        ],
        methods: [
            'getList',
            'onChangeLanguage',
            'getPromotionColumns',
            'updateTotal',
            'onDuplicatePromotion',
            'deleteDisabledTooltip',
        ],
    },
});
