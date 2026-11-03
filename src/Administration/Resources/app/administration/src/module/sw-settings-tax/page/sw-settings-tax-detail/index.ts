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
    name: 'sw-settings-tax-detail',
    load: () => import('./src/sw-settings-tax-detail'),
    public: {
        props: [
            'taxId',
        ],
        blocks: [
            'sw_settings_tax_detail',
            'sw_settings_tax_detail_header',
            'sw_settings_tax_detail_actions',
            'sw_settings_tax_detail_actions_abort',
            'sw_settings_tax_detail_actions_save',
            'sw_settings_tax_detail_content',
            'sw_settings_tax_detail_content_card',
            'sw_settings_tax_detail_content_field_name',
            'sw_settings_tax_detail_content_field_tax_rate',
            'sw_settings_tax_detail_content_field_default_tax_rate',
            'sw_settings_tax_detail_rule_card',
            'sw_settings_tax_detail_custom_field_sets',
        ],
        computed: [
            'identifier',
            'taxRepository',
            'isNewTax',
            'allowSave',
            'tooltipSave',
            'isShopwareDefaultTax',
            'label',
            'showCustomFields',
            'isDefaultTaxRate',
        ],
        methods: [
            'createdComponent',
            'loadCustomFieldSets',
            'onSave',
            'onCancel',
            'invalidateTaxCaches',
            'abortOnLanguageChange',
            'saveOnLanguageChange',
            'onChangeLanguage',
            'changeName',
            'reloadDefaultTaxRate',
            'onChangeDefaultTaxRate',
        ],
    },
});
