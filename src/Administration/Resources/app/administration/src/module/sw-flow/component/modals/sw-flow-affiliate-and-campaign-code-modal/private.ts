import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-flow-affiliate-and-campaign-code-modal',
    load: () => import('./src/sw-flow-affiliate-and-campaign-code-modal'),
    public: {
        props: [
            'sequence',
            'action',
        ],
        emits: [
            'process-finish',
            'modal-close',
        ],
        blocks: [
            'sw_flow_affiliate_and_campaign_code_modal',
            'sw_flow_affiliate_and_campaign_code_modal_content',
            'sw_flow_affiliate_and_campaign_code_modal_entity',
            'sw_flow_affiliate_and_campaign_code_modal_affiliate_code',
            'sw_flow_affiliate_and_campaign_code_modal_affiliate_code_overwrite',
            'sw_flow_affiliate_and_campaign_code_modal_campaign_code',
            'sw_flow_affiliate_and_campaign_code_modal_campaign_code_overwrite',
            'sw_flow_affiliate_and_campaign_code_modal_footer_cancel_button',
            'sw_flow_affiliate_and_campaign_code_modal_footer_save_button',
        ],
        computed: [
            'entityOptions',
        ],
        methods: [
            'createdComponent',
            'fieldError',
            'onSave',
            'onClose',
        ],
    },
});
