import template from './sw-flow-detail.html.twig';
import './sw-flow-detail.scss';

import { ACTION } from '../../constant/flow.constant';

const { Component, Mixin, Context, State } = Shopware;
const { Criteria } = Shopware.Data;
const { ShopwareError } = Shopware.Classes;
const { mapState, mapGetters } = Component.getComponentHelper();

Component.register('sw-flow-detail', {
    template,

    inject: [
        'acl',
        'repositoryFactory',
    ],

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification'),
    ],

    props: {
        flowId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
        };
    },

    computed: {
        flowRepository() {
            return this.repositoryFactory.create('flow');
        },

        isNewFlow() {
            return !this.flowId;
        },

        flowCriteria() {
            const criteria = new Criteria();

            criteria.addAssociation('sequences.rule');
            criteria.getAssociation('sequences')
                .addSorting(Criteria.sort('parentId', 'ASC'))
                .addSorting(Criteria.sort('trueCase', 'ASC'))
                .addSorting(Criteria.sort('position', 'ASC'))
                .addSorting(Criteria.sort('displayGroup', 'ASC'));

            return criteria;
        },

        stateMachineStateRepository() {
            return this.repositoryFactory.create('state_machine_state');
        },

        stateMachineStateCriteria() {
            const criteria = new Criteria();
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

        ...mapState('swFlowState', ['flow']),
        ...mapGetters('swFlowState', ['sequences']),
    },

    watch: {
        flowId() {
            this.getDetailFlow();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.flowId) {
                this.getDetailFlow();
                return;
            }

            this.createNewFlow();
        },

        routeDetailTab(tabName) {
            if (!tabName) return '';

            if (this.isNewFlow) {
                return `sw.flow.create.${tabName}`;
            }

            return `sw.flow.detail.${tabName}`;
        },

        createNewFlow() {
            const flow = this.flowRepository.create();
            flow.priority = 0;
            flow.eventName = '';

            State.commit('swFlowState/setFlow', flow);
        },

        getDetailFlow() {
            this.isLoading = true;
            return this.flowRepository.get(this.flowId, Context.api, this.flowCriteria)
                .then((data) => {
                    State.commit('swFlowState/setFlow', data);
                    this.getDataForActionDescription();
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-flow.flowNotification.messageError'),
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onSave() {
            if (!this.flow.eventName) {
                Shopware.State.dispatch('error/addApiError',
                    {
                        expression: `flow.${this.flow.id}.eventName`,
                        error: new ShopwareError({
                            code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                        }),
                    });

                this.createNotificationWarning({
                    message: this.$tc('sw-flow.flowNotification.messageRequiredEventName'),
                });

                return null;
            }

            // Remove selector sequence type before saving
            this.removeAllSelectors();

            // Validate condition sequence which has empty rule or action sequence has empty action name
            const invalidSequences = this.validateEmptySequence();

            if (invalidSequences.length) {
                this.createNotificationWarning({
                    message: this.$tc('sw-flow.flowNotification.messageRequiredEmptyFields'),
                });

                return null;
            }

            this.isSaveSuccessful = false;
            this.isLoading = true;

            return this.flowRepository.save(this.flow)
                .then(() => {
                    if (typeof this.flow.isNew === 'function' && this.flow.isNew()) {
                        this.$router.push({
                            name: 'sw.flow.detail',
                            params: { id: this.flow.id },
                        });
                    } else {
                        this.getDetailFlow();
                    }

                    this.createNotificationSuccess({
                        message: this.$tc('sw-flow.flowNotification.messageSaveSuccess'),
                    });

                    this.isSaveSuccessful = true;
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-flow.flowNotification.messageSaveError'),
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        saveFinish() {
            this.isLoading = false;
            this.isSaveSuccessful = false;
        },

        removeAllSelectors() {
            const newSequences = this.sequences.filter(sequence => {
                return sequence.ruleId !== null || sequence.actionName !== null;
            });

            State.commit('swFlowState/setSequences', newSequences);
        },

        validateEmptySequence() {
            const invalidSequences = this.sequences.reduce((result, sequence) => {
                if (sequence.ruleId === '' || sequence.actionName === '') {
                    result.push(sequence.id);
                }

                return result;
            }, []);

            State.commit('swFlowState/setInvalidSequences', invalidSequences);

            return invalidSequences;
        },

        getDataForActionDescription() {
            if (!this.sequences) {
                return null;
            }

            const promises = [];
            const hasSetOrderStateAction = this.sequences.some(sequence => sequence.actionName === ACTION.SET_ORDER_STATE);

            if (hasSetOrderStateAction) {
                // get support information for set order state action.
                promises.push(this.stateMachineStateRepository.search(this.stateMachineStateCriteria)
                    .then(data => {
                        State.commit('swFlowState/setStateMachineState', data);
                    }));
            }

            return Promise.all(promises);
        },
    },
});
