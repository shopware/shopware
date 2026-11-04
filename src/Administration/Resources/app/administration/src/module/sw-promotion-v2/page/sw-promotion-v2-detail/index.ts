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
    name: 'sw-promotion-v2-detail',
    load: () => import('./src/sw-promotion-v2-detail'),
    public: {
        props: [
            'promotionId',
        ],
        blocks: [
            'sw_promotion_v2_detail',
            'sw_promotion_v2_detail_header',
            'sw_promotion_v2_detail_actions',
            'sw_promotion_v2_detail_actions_abort',
            'sw_promotion_v2_detail_actions_save',
            'sw_promotion_v2_detail_language_switch',
            'sw_promotion_v2_detail_content',
            'sw_promotion_v2_detail_content_language_info',
            'sw_promotion_v2_detail_content_tabs',
            'sw_promotion_v2_detail_content_mt_tabs',
            'sw_promotion_v2_detail_content_tabs_general',
            'sw_promotion_v2_detail_content_tabs_conditions',
            'sw_promotion_v2_detail_content_tabs_discounts',
            'sw_promotion_v2_detail_content_view',
            'sw_promotion_v2_detail_change_code_type_modal',
            'sw_promotion_v2_detail_change_code_type_modal_footer',
            'sw_promotion_v2_detail_change_code_type_modal_footer_cancel_action',
            'sw_promotion_v2_detail_change_code_type_modal_footer_confirm_action',
        ],
        computed: [
            'identifier',
            'promotionRepository',
            'isCreateMode',
            'promotionCriteria',
            'tooltipSave',
            'tooltipCancel',
            'promotionGroupRepository',
            'promotionDetailTabs',
        ],
        methods: [
            'createdComponent',
            'loadEntityData',
            'onChangeLanguage',
            'onSave',
            'onConfirmSave',
            'onCloseCodeTypeChangeModal',
            'savePromotion',
            'savePromotionSetGroups',
            'saveFinish',
            'onCancel',
            'onCleanUpCodes',
            'cleanUpCodes',
            'onGenerateIndividualCodesFinish',
            'onDeleteIndividualCodesFinish',
        ],
    },
});
