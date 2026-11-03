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
    name: 'sw-extension-review-reply',
    load: () => import('./src/sw-extension-review-reply'),
    public: {
        props: [
            'reply',
            'producerName',
        ],
        blocks: [
            'sw_extension_review_reply',
            'sw_extension_review_reply_head',
            'sw_extension_review_reply_head_producer_name',
            'sw_extension_review_reply_head_reply_date',
            'sw_extension_review_reply_text',
        ],
        computed: [
            'creationDate',
        ],
    },
});
