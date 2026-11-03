import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-order-nested-line-items-row',
    load: () => import('./src/sw-order-nested-line-items-row'),
    public: {
        props: [
            'lineItem',
            'currency',
            'renderParent',
        ],
        blocks: [
            'sw_order_nested_line_items_row',
            'sw_order_nested_line_items_row_content',
            'sw_order_nested_line_items_row_content_label',
            'sw_order_nested_line_items_row_content_label_nesting',
            'sw_order_nested_line_items_row_content_label_content',
            'sw_order_nested_line_items_row_content_unit_price',
            'sw_order_nested_line_items_row_content_unit_price_content',
            'sw_order_nested_line_items_row_content_quantity',
            'sw_order_nested_line_items_row_content_quantity_content',
            'sw_order_nested_line_items_row_content_total_price',
            'sw_order_nested_line_items_row_content_total_price_content',
            'sw_order_nested_line_items_row_content_tax',
            'sw_order_nested_line_items_row_content_tax_content',
            'sw_order_nested_line_items_row_nesting',
        ],
        computed: [
            'currencyFilter',
        ],
        methods: [
            'getNestingClasses',
        ],
    },
});
