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
    name: 'sw-extension-review',
    load: () => import('./src/sw-extension-review'),
    public: {
        props: [
            'review',
            'producerName',
        ],
        blocks: [
            'sw_extension_review',
            'sw_extension_review_headline',
            'sw_extension_review_stars',
            'sw_extension_review_text',
            'sw_extension_review_meta_data',
            'sw_extension_review_meta_data_date',
            'sw_extension_review_meta_data_version',
            'sw_extension_review_replies',
            'sw_extension_review_replies_loop',
        ],
        computed: [
            'lastChangeDate',
            'reviewHasReplies',
        ],
    },
});
