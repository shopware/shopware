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
    name: 'sw-order-saveable-field',
    load: () => import('./src/sw-order-saveable-field'),
    public: {
        props: [
            'value',
            'type',
            'placeholder',
            'editable',
        ],
        emits: [
            'value-change',
            'update:value',
        ],
        slots: [
            'default',
        ],
        blocks: [
            'sw_order_saveable_field',
            'sw_order_saveable_field_input',
            'sw_order_saveable_field_input_field',
            'sw_order_saveable_field_actions',
            'sw_order_saveable_field_cancel_button',
            'sw_order_saveable_field_save_button',
            'sw_order_saveable_field_content',
        ],
        computed: [
            'component',
            'valuePropName',
            'computedAttrs',
        ],
        methods: [
            'onClick',
            'onSaveButtonClicked',
            'onCancelButtonClicked',
        ],
    },
});
