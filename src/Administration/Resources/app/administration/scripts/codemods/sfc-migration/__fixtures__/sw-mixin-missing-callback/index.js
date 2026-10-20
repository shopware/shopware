import template from './sw-mixin-missing-callback.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    // Nothing here answers the `selectableItems` the mixin expected its host to override.
    mixins: [
        Shopware.Mixin.getByName('media-grid-listener'),
    ],

    props: {
        items: {
            type: Array,
            required: true,
        },
    },
};
