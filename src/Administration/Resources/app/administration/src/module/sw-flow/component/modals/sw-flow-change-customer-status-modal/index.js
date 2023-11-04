import template from './sw-flow-change-customer-status-modal.html.twig';

const { Component } = Shopware;
const { mapState } = Component.getComponentHelper();

/**
 * @private
 * @package business-ops
 */
export default {
    template,

    inject: ['repositoryFactory'],

    props: {
        sequence: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            active: true,
            fieldError: null,
        };
    },

    computed: {
        ...mapState('swFlowState', ['customerStatus']),

        options() {
            return [
                { value: true, label: this.$tc('sw-flow.modals.customerStatus.active') },
                { value: false, label: this.$tc('sw-flow.modals.customerStatus.inactive') },
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.sequence?.config) {
                this.active = this.sequence?.config.active;
                return;
            }
            this.active = true;
        },

        onClose() {
            this.$emit('modal-close');
        },

        onAddAction() {
            this.sequence.config = { active: this.active };

            this.$emit('process-finish', this.sequence);
        },
    },
};
