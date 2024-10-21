import template from './sw-order-state-change-modal-attach-documents.html.twig';
import './sw-order-state-change-modal-attach-documents.scss';

/**
 * @package checkout
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    emits: ['on-confirm'],

    props: {
        order: {
            type: Object,
            required: true,
        },

        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            sendMail: true,
        };
    },

    methods: {
        onConfirm() {
            const docIds = [];
            this.$refs.attachDocuments.documents.forEach((doc) => {
                if (doc.attach) {
                    docIds.push(doc.id);
                }
            });

            this.$emit('on-confirm', docIds, this.sendMail);
        },
    },
};
