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
    name: 'sw-promotion-v2-generate-codes-modal',
    load: () => import('./src/sw-promotion-v2-generate-codes-modal'),
    public: {
        props: [
            'promotion',
        ],
        emits: [
            'generate-finish',
            'close',
        ],
        blocks: [
            'sw_promotion_v2_generate_codes_modal',
            'sw_promotion_v2_generate_codes_modal_content',
            'sw_promotion_v2_generate_codes_modal_warning',
            'sw_promotion_v2_generate_codes_modal_description',
            'sw_promotion_v2_generate_codes_modal_container_pattern',
            'sw_promotion_v2_generate_codes_modal_container_pattern_simple',
            'sw_promotion_v2_generate_codes_modal_prefix',
            'sw_promotion_v2_generate_codes_modal_replacement',
            'sw_promotion_v2_generate_codes_modal_suffix',
            'sw_promotion_v2_generate_codes_modal_container_pattern_custom',
            'sw_promotion_v2_generate_codes_modal_custom_pattern_switch',
            'sw_promotion_v2_generate_codes_modal_container_generate',
            'sw_promotion_v2_generate_codes_modal_preview',
            'sw_promotion_v2_generate_codes_modal_code_amount',
            'sw_promotion_v2_generate_codes_modal_footer',
            'sw_promotion_v2_generate_codes_modal_cancel',
            'sw_promotion_v2_generate_codes_modal_generate',
        ],
        computed: [
            'hasValidPattern',
            'patternError',
        ],
        methods: [
            'createdComponent',
            'updatePattern',
            'updatePreview',
            'onGenerate',
            'onClose',
        ],
    },
});
