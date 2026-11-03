import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@after-sales
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
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
