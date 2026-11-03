import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package inventory
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-product-add-properties-modal',
    load: () => import('./src/sw-product-add-properties-modal'),
    public: {
        props: [
            'newProperties',
            'propertiesAvailable',
        ],
        emits: [
            'modal-cancel',
            'modal-save',
        ],
        slots: [
            'toolbar',
            'toolbar-search-field',
        ],
        blocks: [
            'sw_product_add_properties_modal',
            'sw_product_add_properties_modal_filled_state',
            'sw_product_add_properties_modal_filled_state_header',
            'sw_product_add_properties_modal_filled_state_search_field',
            'sw_property_search_field',
            'sw_productadd_properties_selection_search_field',
            'sw_product_add_properties_modal_button_cancel',
            'sw_product_add_properties_modal_button_save',
        ],
        computed: [
            'showSaveButton',
            'assetFilter',
        ],
        methods: [
            'onCancel',
            'onSave',
            'onOpenProperties',
            'onSelectOption',
        ],
    },
});
