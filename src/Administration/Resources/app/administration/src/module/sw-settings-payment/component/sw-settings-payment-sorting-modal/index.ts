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
    name: 'sw-settings-payment-sorting-modal',
    load: () => import('./src/sw-settings-payment-sorting-modal'),
    public: {
        props: [
            'paymentMethods',
        ],
        emits: [
            'modal-close',
            'modal-save',
        ],
        blocks: [
            'sw_settings_payment_sorting_modal',
            'sw_settings_payment_sorting_modal_content',
            'sw_settings_payment_sorting_modal_content_subtitle',
            'sw_settings_payment_sorting_modal_content_list',
            'sw_settings_payment_sorting_modal_content_payment_method',
            'sw_settings_payment_sorting_modal_content_payment_method_action',
            'sw_settings_payment_sorting_modal_content_payment_method_icon',
            'sw_settings_payment_sorting_modal_content_payment_method_name',
            'sw_settings_payment_sorting_modal_footer',
            'sw_settings_payment_sorting_modal_footer_cancel_button',
            'sw_settings_payment_sorting_modal_footer_save_button',
        ],
        computed: [
            'paymentMethodRepository',
            'assetFilter',
        ],
        methods: [
            'closeModal',
            'applyChanges',
            'onSort',
            'isShopwareDefaultPaymentMethod',
        ],
    },
});
