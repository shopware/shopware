import template from './sw-flow-detail.html.twig';
import './sw-flow-detail.scss';

import { ACTION } from '../../constant/flow.constant';

const { Component, Mixin, Context, State } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState, mapGetters, mapPropertyErrors } = Component.getComponentHelper();

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

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier() {
            return this.flow?.name;
        },

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
                .addSorting(Criteria.sort('displayGroup', 'ASC'))
                .addSorting(Criteria.sort('parentId', 'ASC'))
                .addSorting(Criteria.sort('trueCase', 'ASC'))
                .addSorting(Criteria.sort('position', 'ASC'));

            return criteria;
        },

        documentTypeRepository() {
            return this.repositoryFactory.create('document_type');
        },

        documentTypeCriteria() {
            const criteria = new Criteria(1, 100);
            criteria.addSorting(Criteria.sort('name', 'ASC'));
            return criteria;
        },

        mailTemplateRepository() {
            return this.repositoryFactory.create('mail_template');
        },

        mailTemplateIdsCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('mailTemplateType');
            criteria.addFilter(Criteria.equalsAny('id', this.mailTemplateIds));
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
        ...mapGetters('swFlowState', ['sequences', 'mailTemplateIds']),
        ...mapPropertyErrors('flow', ['name', 'eventName']),
    },

    watch: {
        flowId() {
            this.getDetailFlow();
        },
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        this.beforeDestroyComponent();
    },

    methods: {
        createdComponent() {
            if (this.flowId) {
                this.getDetailFlow();
                return;
            }

            this.createNewFlow();
        },

        beforeDestroyComponent() {
            State.dispatch('swFlowState/resetFlowState');
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

                    this.isSaveSuccessful = true;
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-flow.flowNotification.messageSaveError'),
                    });

                    this.handleFieldValiationError();
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        handleFieldValiationError() {
            if (!this.flowNameError && !this.flowEventNameError) {
                return;
            }

            const currentRouteName = this.$router.history.current.name;

            const hasErrorTabFlow = (currentRouteName === 'sw.flow.create.flow'
               || currentRouteName === 'sw.flow.detail.flow')
               && this.flowEventNameError;

            const hasErrorTabGeneral = (currentRouteName === 'sw.flow.create.general'
                || currentRouteName === 'sw.flow.detail.general')
                && this.flowNameError;

            if (hasErrorTabFlow || hasErrorTabGeneral) {
                return;
            }

            // Navigate to another tab which contains field errors
            if (this.flowId) {
                this.$router.push({
                    name: this.flowNameError
                        ? 'sw.flow.detail.general'
                        : 'sw.flow.detail.flow',
                    params: { flowId: this.flowId },
                });

                return;
            }

            this.$router.push({
                name: this.flowNameError
                    ? 'sw.flow.create.general'
                    : 'sw.flow.create.flow',
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

            const hasDocumentAction = this.sequences.some(sequence => sequence.actionName === ACTION.GENERATE_DOCUMENT);

            if (hasDocumentAction) {
                // get support information for generate document action.
                promises.push(this.documentTypeRepository.search(this.documentTypeCriteria).then((data) => {
                    Shopware.State.commit('swFlowState/setDocumentTypes', data);
                }));
            }

            const hasMailSendAction = this.sequences.some(sequence => sequence.actionName === ACTION.MAIL_SEND);

            if (hasMailSendAction) {
                // get support information for mail send action.
                promises.push(this.mailTemplateRepository.search(this.mailTemplateIdsCriteria).then((data) => {
                    Shopware.State.commit('swFlowState/setMailTemplates', data);
                }));
            }

            return Promise.all(promises);
        },
    },
});
