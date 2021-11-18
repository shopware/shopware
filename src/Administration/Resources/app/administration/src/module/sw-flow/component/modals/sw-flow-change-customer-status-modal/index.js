import template from './sw-flow-change-customer-status-modal.html.twig';

const { Component } = Shopware;
const { mapState } = Component.getComponentHelper();

Component.register('sw-flow-change-customer-status-modal', {
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
            this.active = this.sequence?.config?.active || true;
        },

        onClose() {
            this.$emit('modal-close');
        },

        onAddAction() {
            this.sequence.config = { active: this.active };

            this.$emit('process-finish', this.sequence);
        },
    },
});
