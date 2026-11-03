import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-settings-rule-add-assignment-listing',
    load: () => import('./src/sw-settings-rule-add-assignment-listing'),
    public: {
        props: [
            'ruleId',
            'entityContext',
        ],
        emits: [
            'select-item',
        ],
        blocks: [
            'sw_settings_rule_add_assignment_listing',
            'sw_settings_rule_add_assignment_listing_toolbar',
            'sw_settings_rule_add_assignment_listing_grid',
            'sw_settings_rule_add_assignment_listing_checkbox',
            'sw_settings_rule_add_assignment_listing_name',
            'sw_settings_rule_add_assignment_listing_active_flag',
            'sw_settings_rule_add_assignment_listing_tax_type',
            'sw_settings_rule_add_assignment_listing_pagination',
        ],
        computed: [
            'criteria',
            'shippingCostTaxOptions',
        ],
        methods: [
            'createdComponent',
            'onSelectItem',
            'isNotAssigned',
            'paginate',
            'doSearch',
            'shippingTaxTypeLabel',
        ],
    },
});
