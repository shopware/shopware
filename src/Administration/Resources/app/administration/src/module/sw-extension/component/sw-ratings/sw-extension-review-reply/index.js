import template from './sw-extension-review-reply.html.twig';
import './sw-extension-review-reply.scss';
import { date } from 'shopware:utils/format';

/**
 * @private
 * @sw-package checkout
 */
export default {
    template,

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
