import template from './sw-flow-set-order-state-modal.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState } = Component.getComponentHelper();

/**
 * @private
 * @package business-ops
 */
export default {
    template,

    inject: [
        'repositoryFactory',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        sequence: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            paymentOptions: [],
            deliveryOptions: [],
            orderOptions: [],
            config: {
                order: '',
                order_delivery: '',
                order_transaction: '',
                force_transition: false,
            },
        };
    },

    computed: {
        stateMachineStateRepository() {
            return this.repositoryFactory.create('state_machine_state');
        },

        stateMachineStateCriteria() {
            const criteria = new Criteria(1, null);
            criteria.addSorting({ field: 'name', order: 'ASC' });
            criteria.addAssociation('stateMachine');
            criteria.addFilter(
                Criteria.equalsAny(
                    'state_machine_state.stateMachine.technicalName',
                    ['order.state', 'order_transaction.state', 'order_delivery.state'],
                ),
            );

            return criteria;
        },

        ...mapState('swFlowState', ['stateMachineState']),
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.config = this.sequence?.config ? { ...this.sequence?.config } : this.config;

            if (!this.stateMachineState.length) {
                this.getAllStates();
            } else {
                this.generateOptions(this.stateMachineState);
            }
        },

        getAllStates() {
            return this.stateMachineStateRepository.search(this.stateMachineStateCriteria)
                .then(data => {
                    this.generateOptions(data);
                    Shopware.State.commit('swFlowState/setStateMachineState', data);
                });
        },

        generateOptions(data) {
            this.paymentOptions = this.buildTransitionOptions(
                'order_transaction.state',
                data,
            );

            this.deliveryOptions = this.buildTransitionOptions(
                'order_delivery.state',
                data,
            );

            this.orderOptions = this.buildTransitionOptions(
                'order.state',
                data,
            );
        },

        buildTransitionOptions(stateMachineName, allTransitions) {
            const entries = allTransitions.filter((entry) => {
                return entry.stateMachine.technicalName === stateMachineName;
            });

            return entries.map((state) => {
                return {
                    id: state.technicalName,
                    name: state.translated.name,
                };
            });
        },

        onClose() {
            this.$emit('modal-close');
        },

        onAddAction() {
            if (!this.config.order && !this.config.order_delivery && !this.config.order_transaction) {
                this.createNotificationError({
                    message: this.$tc('sw-flow.modals.status.messageNoStatusError'),
                });
                return;
            }

            const sequence = {
                ...this.sequence,
                config: this.config,
            };

            this.$emit('process-finish', sequence);
        },
    },
};
