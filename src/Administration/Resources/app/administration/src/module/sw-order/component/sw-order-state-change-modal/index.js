import template from './sw-order-state-change-modal.html.twig';
import './sw-order-state-change-modal.scss';

/**
 * @package checkout
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    emits: ['page-leave', 'page-leave-confirm'],

    props: {
        order: {
            type: Object,
            required: true,
        },

        isLoading: {
            type: Boolean,
            required: true,
        },

        technicalName: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            showModal: false,
            userCanConfirm: false,
        };
    },

    computed: {
        modalTitle() {
            return this.$tc('sw-order.assignMailTemplateCard.cardTitle');
        },
    },

    methods: {
        onCancel() {
            this.$emit('page-leave');
        },

        onDocsConfirm(docIds, sendMail = true) {
            this.$emit('page-leave-confirm', docIds, sendMail);
        },
    },
};
