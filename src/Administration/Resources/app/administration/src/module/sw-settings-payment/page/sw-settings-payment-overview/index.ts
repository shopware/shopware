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
    name: 'sw-settings-payment-overview',
    load: () => import('./src/sw-settings-payment-overview'),
    public: {
        blocks: [
            'sw_settings_payment_overview',
            'sw_settings_payment_overview_smart_bar_actions',
            'sw_settings_payment_overview_smart_bar_actions_add',
            'sw_settings_payment_overview_smart_bar_header',
            'sw_settings_payment_overview_smart_bar_header_title',
            'sw_settings_payment_overview_language_switch',
            'sw_settings_payment_overview_content',
            'sw_settings_payment_overview_content_inner',
            'sw_settings_payment_overview_sorting_modal_card',
            'sw_settings_payment_overview_sorting_modal_card_button',
            'sw_settings_payment_overview_list',
            'sw_settings_payment_overview_list_card_custom_location',
            'sw_settings_payment_overview_list_card_custom_card',
            'sw_settings_payment_overview_list_card_default',
            'sw_settings_payment_overview_empty_state',
            'sw_settings_payment_overview_sorting_modal',
        ],
        computed: [
            'customCards',
            'paymentMethodRepository',
            'paymentMethodCriteria',
            'isEmpty',
            'paymentMethodCards',
        ],
        methods: [
            'createdComponent',
            'loadPaymentMethods',
            'onChangeLanguage',
            'togglePaymentMethodActive',
            'showActivationSuccessNotification',
            'showActivationErrorNotification',
        ],
    },
});
