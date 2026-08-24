import template from './sw-mixin-missing-prop.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    // The `item` the mixin read was its own prop; nothing declares it here.
    mixins: [
        Shopware.Mixin.getByName('video-cover'),
    ],

    props: {
        media: {
            type: Object,
            required: true,
        },
    },
};
