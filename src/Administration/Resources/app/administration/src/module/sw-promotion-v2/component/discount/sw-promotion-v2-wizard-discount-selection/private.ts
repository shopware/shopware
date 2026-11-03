import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module and what it inherits from sw-wizard-page,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-promotion-v2-wizard-discount-selection',
    extends: 'sw-wizard-page',
    load: () => import('./src/sw-promotion-v2-wizard-discount-selection'),
    public: {
        props: [
            'isActive',
            'title',
            'position',
        ],
        emits: [
            'change-selection',
        ],
        slots: [
            'default',
        ],
        blocks: [
            'sw_wizard_page_slot_default',
            'sw_wizard_discount_selection',
            'sw_wizard_discount_selection_option_selection',
            'sw_wizard_page',
        ],
        methods: [
            'getSelectionOptions',
            'onChangeSelection',
            'createdComponent',
            'destroyedComponent',
        ],
    },
});
