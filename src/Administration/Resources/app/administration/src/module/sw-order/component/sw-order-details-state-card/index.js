import template from './sw-order-details-state-card.html.twig';
import './sw-order-details-state-card.scss';

/**
 * @package customer-order
 */

const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
        'repositoryFactory',
        'orderStateMachineService',
        'stateMachineService',
        'stateStyleDataProviderService',
    ],

    mixins: [
        'notification',
    ],

    props: {
        order: {
            type: Object,
            required: true,
        },
        title: {
            type: String,
            required: false,
            default: '',
        },
        entity: {
            type: Object,
            required: true,
        },
        stateLabel: {
            type: String,
            required: false,
            default: '',
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            statesLoading: false,
            stateOptions: [],
            lastStateChange: null,
            currentActionName: null,
            showStateChangeModal: false,
            stateChangeModalConfirmed: false,
        };
    },

    computed: {
        stateMachineStateRepository() {
            return this.repositoryFactory.create('state_machine_state');
        },

        stateMachineHistoryRepository() {
            return this.repositoryFactory.create('state_machine_history');
        },

        stateMachineStateCriteria() {
            const criteria = new Criteria(1, null);
            criteria.addSorting({ field: 'name', order: 'ASC' });
            criteria.addAssociation('stateMachine');
            criteria.addFilter(
                Criteria.equals(
                    'state_machine_state.stateMachine.technicalName',
                    `${this.entityName}.state`,
                ),
            );

            return criteria;
        },

        stateMachineHistoryCriteria() {
            const criteria = new Criteria(1, 1);

            criteria.addFilter(Criteria.equals('entityId.id', this.entity.id));
            criteria.addFilter(Criteria.equals('entityName', this.entityName));
            criteria.addAssociation('user');
            criteria.addSorting({ field: 'createdAt', order: 'DESC' });

            return criteria;
        },

        entityName() {
            return this.entity.getEntityName();
        },

        stateName() {
            return this.entity.stateMachineState.translated.name;
        },

        stateSelectBackgroundStyle() {
            const technicalName = this.entity.stateMachineState.technicalName;

            return this.stateStyleDataProviderService.getStyle(
                `${this.entityName}.state`,
                technicalName,
            ).selectBackgroundStyle;
        },

        stateTransitionMethod() {
            switch (this.entityName) {
                case 'order':
                    return this.orderStateMachineService.transitionOrderState.bind(this.orderStateMachineService);
                case 'order_transaction':
                    return this.orderStateMachineService.transitionOrderTransactionState.bind(this.orderStateMachineService);
                case 'order_delivery':
                    return this.orderStateMachineService.transitionOrderDeliveryState.bind(this.orderStateMachineService);
                default:
                    return null;
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getTransitionOptions();
            this.getLastChange();
        },

        onShowStatusHistory() {
            this.$emit('show-status-history');
        },

        getTransitionOptions() {
            this.statesLoading = true;

            return Promise.all([
                this.stateMachineStateRepository.search(this.stateMachineStateCriteria),
                this.stateMachineService.getState(this.entityName, this.entity.id),
            ]).then((data) => {
                this.stateOptions = this.buildTransitionOptions(
                    data[0],
                    data[1].data.transitions,
                );

                this.statesLoading = false;
                return Promise.resolve();
            });
        },

        buildTransitionOptions(allTransitions, possibleTransitions) {
            const options = allTransitions.map((state, index) => {
                return {
                    stateName: state.technicalName,
                    id: index,
                    name: state.translated.name,
                    disabled: true,
                };
            });

            options.forEach((option) => {
                const transitionToState = possibleTransitions.filter((transition) => {
                    return transition.toStateName === option.stateName;
                });

                if (transitionToState.length >= 1) {
                    option.disabled = false;
                    option.id = transitionToState[0].actionName;
                }
            });

            return options;
        },

        onStateSelected(stateType, actionName) {
            if (!stateType || !actionName) {
                this.createStateChangeErrorNotification(this.$tc('sw-order.stateCard.labelErrorNoAction'));
                return;
            }

            if (!this.modalConfirmed) {
                this.currentActionName = actionName;
                this.showStateChangeModal = true;

                return;
            }

            this.stateChangeModalConfirmed = false;
        },

        onLeaveModalClose() {
            this.stateChangeModalConfirmed = false;
            this.currentActionName = null;
            this.showStateChangeModal = false;
        },

        onLeaveModalConfirm(docIds, sendMail = true) {
            this.showStateChangeModal = false;

            this.stateTransitionMethod(
                this.entity.id,
                this.currentActionName,
                { documentIds: docIds, sendMail },
            ).then(() => {
                this.getLastChange();

                return this.getTransitionOptions();
            }).then(() => {
                this.$emit('save-edits');
            }).catch((error) => {
                this.createStateChangeErrorNotification(error);
            })
                .finally(() => {
                    this.stateChangeModalConfirmed = false;
                    this.currentActionName = null;
                });
        },

        createStateChangeErrorNotification(errorMessage) {
            this.createNotificationError({
                message: this.$tc('sw-order.stateCard.labelErrorStateChange') + errorMessage,
            });
        },

        getLastChange() {
            this.lastStateChange = null;
            this.stateMachineHistoryRepository.search(this.stateMachineHistoryCriteria).then((result) => {
                this.lastStateChange = result.first();
            });
        },
    },

};
