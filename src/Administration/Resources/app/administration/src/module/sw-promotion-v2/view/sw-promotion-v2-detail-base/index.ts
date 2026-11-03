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
    name: 'sw-promotion-v2-detail-base',
    load: () => import('./src/sw-promotion-v2-detail-base'),
    public: {
        props: [
            'promotion',
            'isLoading',
            'isCreateMode',
        ],
        emits: [
            'generate-individual-codes-finish',
            'delete-individual-codes-finish',
            'clean-up-codes',
        ],
        blocks: [
            'sw_promotion_v2_detail_base',
            'sw_promotion_v2_detail_base_general_card',
            'sw_promotion_v2_detail_base_general_name',
            'sw_promotion_v2_detail_base_general_valid_container',
            'sw_promotion_v2_detail_base_general_valid_from',
            'sw_promotion_v2_detail_base_general_valid_until',
            'sw_promotion_v2_detail_base_general_max_uses',
            'sw_promotion_v2_detail_base_general_max_uses_global',
            'sw_promotion_v2_detail_base_general_max_uses_customer',
            'sw_promotion_v2_detail_base_general_active',
            'sw_promotion_v2_detail_base_codes_card',
            'sw_promotion_v2_detail_base_codes_select',
            'sw_promotion_v2_detail_base_codes_type_fixed',
            'sw_promotion_v2_detail_base_codes_type_fixed_code',
            'sw_promotion_v2_detail_base_codes_type_fixed_generate',
            'sw_promotion_v2_detail_base_codes_type_individual',
            'sw_promotion_v2_detail_base_codes_type_individual_codes_behavior',
            'sw_promotion_detail_custom_field_sets',
        ],
        computed: [
            'codeTypeOptions',
            'showCustomFields',
        ],
        methods: [
            'createdComponent',
            'initialSort',
            'onChangeCodeType',
            'setNewCodeType',
            'loadCustomFieldSets',
            'onGenerateCodeFixed',
            'generateFinish',
        ],
    },
});
