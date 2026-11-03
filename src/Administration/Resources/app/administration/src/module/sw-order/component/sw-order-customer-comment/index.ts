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
    name: 'sw-order-customer-comment',
    load: () => import('./src/sw-order-customer-comment'),
    public: {
        props: [
            'customerComment',
            'isLoading',
        ],
        blocks: [
            'sw_order_customer_comment',
            'sw_order_customer_comment_content',
        ],
    },
});
