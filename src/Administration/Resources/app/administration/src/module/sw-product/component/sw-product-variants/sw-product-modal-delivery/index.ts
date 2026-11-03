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
    name: 'sw-product-modal-delivery',
    load: () => import('./src/sw-product-modal-delivery'),
    public: {
        props: [
            'product',
            'selectedGroups',
        ],
        emits: [
            'modal-close',
            'configuration-close',
        ],
        blocks: [
            'sw_product_modal_delivery',
            'sw_product_modal_delivery_sidebar',
            'sw_product_modal_delivery_sidebar_tabs',
            'sw_product_modal_delivery_sidebar_mt_tabs',
            'sw_product_modal_delivery_sidebar_tabs_items',
            'sw_product_modal_delivery_sidebar_descriptions',
            'sw_product_modal_delivery_main',
            'sw_product_modal_delivery_main_order',
            'sw_product_modal_delivery_media',
            'sw_product_modal_delivery_listing',
            'sw_product_modal_delivery_footer',
            'sw_product_modal_delivery_footer_button_cancel',
            'sw_product_modal_delivery_footer_button_save',
        ],
        computed: [
            'productRepository',
            'deliveryTabs',
        ],
        methods: [
            'createdComponent',
            'saveDeliveryConfiguration',
            'cancelDeliveryConfiguration',
            'handleExpandedListing',
        ],
    },
});
