import template from './sw-extension-review-reply.html.twig';
import './sw-extension-review-reply.scss';

const { date } = Shopware.Utils.format;

/**
 * @private
 * @package checkout
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        reply: {
            type: Object,
            required: true,
        },

        producerName: {
            type: String,
            required: true,
        },
    },

    computed: {
        creationDate() {
            return this.reply.creationDate !== null ? date(this.reply.creationDate) : null;
        },
    },
};
