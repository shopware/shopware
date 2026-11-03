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
    name: 'sw-order-nested-line-items-modal',
    load: () => import('./src/sw-order-nested-line-items-modal'),
    public: {
        props: [
            'lineItem',
            'order',
        ],
        emits: [
            'modal-close',
        ],
        blocks: [
            'sw_order_nested_line_item_modal',
            'sw_order_nested_line_item_modal_loader',
            'sw_order_nested_line_item_modal_container',
            'sw_order_nested_line_item_modal_header_container',
            'sw_order_nested_line_item_modal_header_label',
            'sw_order_nested_line_item_modal_header_unit_price',
            'sw_order_nested_line_item_modal_header_quantity',
            'sw_order_nested_line_item_modal_header_total_price',
            'sw_order_nested_line_item_modal_header_tax',
            'sw_order_nested_line_item_modal_content',
            'sw_order_nested_line_item_modal_footer',
            'sw_order_nested_line_item_modal_footer_content',
        ],
        computed: [
            'lineItemRepository',
            'modalTitle',
        ],
        methods: [
            'createdComponent',
            'enrichNestedLineItems',
            'naturalSort',
            'onCloseModal',
        ],
    },
});
