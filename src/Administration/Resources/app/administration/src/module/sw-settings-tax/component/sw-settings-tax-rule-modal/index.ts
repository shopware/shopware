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
    name: 'sw-settings-tax-rule-modal',
    load: () => import('./src/sw-settings-tax-rule-modal'),
    public: {
        props: [
            'tax',
            'currentRule',
        ],
        emits: [
            'modal-close',
        ],
        blocks: [
            'sw_settings_tax_rule_modal',
            'sw_settings_tax_rule_modal_form_first_row',
            'sw_settings_tax_rule_modal_country_select',
            'sw_settings_tax_rule_modal_type_select',
            'sw_settings_tax_rule_modal_form_second_row',
            'sw_settings_tax_rule_modal_additional_component',
            'sw_settings_tax_rule_modal_tax_rate',
            'sw_settings_tax_rule_modal_tax_active_from',
            'sw_settings_tax_rule_modal_form_footer',
            'sw_settings_tax_rule_modal_form_footer_cancel',
            'sw_settings_tax_rule_modal_form_footer_button_create',
        ],
        computed: [
            'taxRuleRepository',
            'taxRuleTypeRepository',
            'additionalComponent',
            'taxRuleTypeCriteria',
            'countryCriteria',
        ],
        methods: [
            'changeRuleType',
            'createdComponent',
            'onConfirm',
        ],
    },
});
