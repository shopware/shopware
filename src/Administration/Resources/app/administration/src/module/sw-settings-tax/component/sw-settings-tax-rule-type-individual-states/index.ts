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
    name: 'sw-settings-tax-rule-type-individual-states',
    load: () => import('./src/sw-settings-tax-rule-type-individual-states'),
    public: {
        props: [
            'taxRule',
        ],
        blocks: [
            'sw_settings_tax_rule_type_individual_states',
        ],
        computed: [
            'exclusionCriteria',
            'stateRepository',
        ],
        methods: [
            'createdComponent',
            'onChange',
        ],
    },
});
