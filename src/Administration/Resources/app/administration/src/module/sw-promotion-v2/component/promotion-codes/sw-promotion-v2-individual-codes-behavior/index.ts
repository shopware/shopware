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
    name: 'sw-promotion-v2-individual-codes-behavior',
    load: () => import('./src/sw-promotion-v2-individual-codes-behavior'),
    public: {
        props: [
            'promotion',
        ],
        emits: [
            'delete-finish',
            'generate-finish',
        ],
        blocks: [
            'sw_promotion_v2_individual_codes_behavior',
            'sw_promotion_v2_individual_codes_behavior_card',
            'sw_promotion_v2_individual_codes_behavior_toolbar',
            'sw_promotion_v2_individual_codes_behavior_toolbar_filter',
            'sw_promotion_v2_individual_codes_behavior_toolbar_filter_add_codes',
            'sw_promotion_v2_individual_codes_behavior_toolbar_filter_generate_codes',
            'sw_promotion_v2_individual_codes_behavior_grid',
            'sw_promotion_v2_individual_codes_behavior_grid_redeemed',
            'sw_promotion_v2_individual_codes_behavior_grid_redeemed_icon',
            'sw_promotion_v2_individual_codes_behavior_grid_redeemed_icon_true',
            'sw_promotion_v2_individual_codes_behavior_grid_redeemed_icon_false',
            'sw_promotion_v2_individual_codes_behavior_grid_redeemed_customer',
            'sw_promotion_v2_individual_codes_behavior_grid_redeemed_customer_link',
            'sw_promotion_v2_individual_codes_behavior_grid_created_at',
            'sw_promotion_v2_individual_codes_behavior_grid_actions',
            'sw_promotion_v2_individual_codes_behavior_grid_actions_route_to_customer',
            'sw_promotion_v2_individual_codes_behavior_grid_actions_delete',
            'sw_promotion_v2_individual_codes_behavior_grid_actions_bulk',
            'sw_promotion_v2_individual_codes_behavior_grid_actions_bulk_delete',
            'sw_promotion_v2_individual_codes_behavior_grid_action_modals',
            'sw_promotion_v2_individual_codes_behavior_grid_action_delete_modal',
            'sw_promotion_v2_individual_codes_behavior_empty_state',
            'sw_promotion_v2_individual_codes_behavior_empty_state_actions',
            'sw_promotion_v2_individual_codes_behavior_empty_state_icon',
            'sw_promotion_v2_individual_codes_behavior_delete_bulk_modal',
            'sw_promotion_v2_individual_codes_behavior_generate_codes_modal',
            'sw_promotion_v2_individual_codes_behavior_add_codes_modal',
            'sw_promotion_v2_individual_codes_behavior_add_codes_modal_content',
            'sw_promotion_v2_individual_codes_behavior_add_codes_modal_description',
            'sw_promotion_v2_individual_codes_behavior_add_codes_modal_code_amount',
            'sw_promotion_v2_individual_codes_behavior_add_codes_modal_footer',
            'sw_promotion_v2_individual_codes_behavior_add_codes_modal_footer_cancel',
            'sw_promotion_v2_individual_codes_behavior_add_codes_modal_footer_confirm',
        ],
        computed: [
            'promotionRepository',
            'customerRepository',
            'deleteConfirmText',
            'codeColumns',
            'assetFilter',
            'dateFilter',
        ],
        methods: [
            'mountedComponent',
            'onSearchTermChange',
            'loadIndividualCodesGrid',
            'onSelectionChange',
            'onCodeSelectionChange',
            'onShowCodeDeleteModal',
            'onShowCodeBulkDeleteModal',
            'onConfirmCodeDelete',
            'onConfirmCodeBulkDelete',
            'onCloseDeleteModal',
            'onCloseBulkDeleteModal',
            'onOpenGenerateCodesModal',
            'onGenerateFinish',
            'onCloseGenerateCodesModal',
            'onOpenAddCodesModal',
            'onAddCodes',
            'onCloseAddCodesModal',
            'routeToCustomer',
            'createRoutingErrorNotification',
        ],
    },
});
