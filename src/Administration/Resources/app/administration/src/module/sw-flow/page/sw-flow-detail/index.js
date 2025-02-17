import template from './sw-flow-detail.html.twig';
import './sw-flow-detail.scss';

const { Component, Mixin, Context, Store, Utils, Service } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;
const { cloneDeep } = Shopware.Utils.object;
const { mapState, mapPropertyErrors } = Component.getComponentHelper();

/**
 * @private
 * @sw-package after-sales
 */
export default {
    template,

    inject: [
        'acl',
        'repositoryFactory',
        'feature',
        'flowBuilderService',
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
            showLeavePageWarningModal: false,
            nextRoute: null,
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

        flowTemplateRepository() {
            return this.repositoryFactory.create('flow_template');
        },

        flowSequenceRepository() {
            return this.repositoryFactory.create('flow_sequence');
        },

        appFlowActionRepository() {
            return this.repositoryFactory.create('app_flow_action');
        },

        isNewFlow() {
            return !this.flowId;
        },

        flowCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('sequences.rule');
            criteria
                .getAssociation('sequences')
                .addSorting(Criteria.sort('displayGroup', 'ASC'))
                .addSorting(Criteria.sort('parentId', 'ASC'))
                .addSorting(Criteria.sort('trueCase', 'ASC'))
                .addSorting(Criteria.sort('position', 'ASC'));

            return criteria;
        },

        flowTemplateCriteria() {
            return new Criteria(1, 25);
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

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        customFieldRepository() {
            return this.repositoryFactory.create('custom_field');
        },

        mailTemplateIdsCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('mailTemplateType');
            criteria.addFilter(Criteria.equalsAny('id', this.mailTemplateIds));

            return criteria;
        },

        customerGroupRepository() {
            return this.repositoryFactory.create('customer_group');
        },

        customerGroupCriteria() {
            const criteria = new Criteria(1, 100);
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },

        appFlowActionCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('app');

            return criteria;
        },

        stateMachineStateRepository() {
            return this.repositoryFactory.create('state_machine_state');
        },

        stateMachineStateCriteria() {
            const criteria = new Criteria(1, null);
            criteria.addSorting({ field: 'name', order: 'ASC' });
            criteria.addAssociation('stateMachine');
            criteria.addFilter(
                Criteria.equalsAny('state_machine_state.stateMachine.technicalName', [
                    'order.state',
                    'order_transaction.state',
                    'order_delivery.state',
                ]),
            );

            return criteria;
        },

        customFieldSetCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equalsAny('id', this.customFieldSetIds));

            return criteria;
        },

        customFieldCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equalsAny('id', this.customFieldIds));

            return criteria;
        },

        ruleRepository() {
            return this.repositoryFactory.create('rule');
        },

        isTemplate() {
            return this.$route.query?.type === 'template';
        },

        isUnknownTrigger() {
            if (!this.flowId || this.isLoading) {
                return false;
            }

            return !this.triggerEvents.some((event) => {
                return event.name === this.flow.eventName;
            });
        },

        ...mapState(
            () => Store.get('swFlow'),
            [
                'flow',
                'triggerEvents',
                'sequences',
                'mailTemplateIds',
                'customFieldSetIds',
                'customFieldIds',
                'hasFlowChanged',
            ],
        ),
        ...mapPropertyErrors('flow', [
            'name',
            'eventName',
        ]),
    },

    watch: {
        flowId() {
            if (!this.$route.params.flowTemplateId) {
                this.getDetailFlow();
            }
        },
    },

    created() {
        this.createdComponent();
    },

    beforeRouteLeave(to, from, next) {
        if (this.flow._isNew) {
            next();
            return;
        }

        if (this.hasFlowChanged) {
            this.nextRoute = next;
            this.showLeavePageWarningModal = true;
        } else {
            next();
        }
    },

    beforeUnmount() {
        this.beforeDestroyComponent();
    },

    methods: {
        createdComponent() {
            Service('flowBuilderService').addLabels({
                entity: 'sw-flow.labelDescription.labelEntity',
                tagIds: 'sw-flow.labelDescription.labelTag',
            });

            Shopware.ExtensionAPI.publishData({
                id: 'sw-flow-detail__flow',
                path: 'flow',
                scope: this,
            });

            this.getAppFlowAction();

            if (this.isTemplate) {
                this.getDetailFlowTemplate();
                return;
            }

            if (this.flowId) {
                this.getDetailFlow();
                return;
            }

            this.createNewFlow();
        },

        beforeDestroyComponent() {
            Store.get('swFlow').resetFlowState();
        },

        routeDetailTab(tabName) {
            if (!tabName) {
                return {};
            }

            if (this.isNewFlow) {
                if (this.$route.params.flowTemplateId) {
                    return {
                        name: `sw.flow.create.${tabName}`,
                        params: {
                            flowTemplateId: this.$route.params.flowTemplateId,
                        },
                    };
                }

                return { name: `sw.flow.create.${tabName}` };
            }

            if (this.isTemplate) {
                return {
                    name: `sw.flow.detail.${tabName}`,
                    query: { type: 'template' },
                };
            }

            return { name: `sw.flow.detail.${tabName}` };
        },

        createNewFlow() {
            if (this.$route.params.flowTemplateId) {
                return this.createFromFlowTemplate();
            }

            const flow = this.flowRepository.create();
            flow.id = Utils.createId();
            flow.priority = 0;
            flow.eventName = '';
            flow.sequences = [];

            return Store.get('swFlow').setFlow(flow);
        },

        getDetailFlow() {
            this.isLoading = true;
            Shopware.Store.get('swFlow').fetchTriggerActions();

            return this.flowRepository
                .get(this.flowId, Context.api, this.flowCriteria)
                .then((data) => {
                    Store.get('swFlow').setFlow(data);
                    Store.get('swFlow').setOriginFlow(cloneDeep(data));
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

        getAppFlowAction() {
            return this.appFlowActionRepository.search(this.appFlowActionCriteria, Shopware.Context.api).then((response) => {
                Store.get('swFlow').setAppActions(response);
            });
        },

        getDetailFlowTemplate() {
            this.isLoading = true;

            return this.flowTemplateRepository
                .get(this.flowId, Context.api, this.flowTemplateCriteria)
                .then((data) => {
                    Store.get('swFlow').setFlow(data);
                    Store.get('swFlow').setOriginFlow(cloneDeep(data));
                    this.getDataForActionDescription();
                    this.getRuleDataForFlowTemplate();
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

        async onSave() {
            // Remove selector sequence type before saving
            this.removeAllSelectors();

            if (!this.flow?.name  || !this.flow?.eventName) {
                this.createNotificationWarning({
                    message: this.$tc('sw-flow.flowNotification.emptyFields.general'),
                });

                return;
            }

            // Validate condition sequence which has empty rule or action sequence has empty action name
            const invalidSequences = this.validateEmptySequence();

            if (invalidSequences.length) {
                this.createNotificationWarning({
                    message: this.$tc('sw-flow.flowNotification.emptyFields.sequences'),
                });

                return;
            }

            this.isSaveSuccessful = false;
            this.isLoading = true;

            if (this.isTemplate) {
                this.createNotificationError({
                    message: this.$tc('sw-flow.flowNotification.messageWarningSave'),
                });

                this.isLoading = false;

                return;
            }

            if (!(typeof this.flow.isNew === 'function' && this.flow.isNew()) && !this.isTemplate) {
                await this.updateSequences();
            }

            this.flowRepository
                .save(this.flow)
                .then(() => {
                    if ((typeof this.flow.isNew === 'function' && this.flow.isNew()) || this.$route.params.flowTemplateId) {
                        this.createNotificationSuccess({
                            message: this.$tc('sw-flow.flowNotification.messageCreateSuccess'),
                        });

                        this.$router.push({
                            name: 'sw.flow.detail',
                            params: { id: this.flow.id },
                        });

                        return;
                    }

                    this.getDetailFlow();
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

        async updateSequences() {
            const sequences = this.sequences.map((item) => {
                item.flowId = this.flow.id;
                return item;
            });

            await this.flowSequenceRepository.sync(sequences);

            const deletedSequenceIds = this.getDeletedSequenceIds();

            if (deletedSequenceIds.length > 0) {
                await this.flowSequenceRepository.syncDeleted(deletedSequenceIds);
            }

            const updateFlow = await this.flowRepository.get(this.flowId, Context.api);

            Object.keys(updateFlow).forEach((key) => {
                if (key !== 'sequences') {
                    updateFlow[key] = this.flow[key];
                }
            });

            Store.get('swFlow').setFlow(updateFlow);
        },

        getDeletedSequenceIds() {
            const sequenceIds = this.sequences.map((sequence) => sequence.id);
            const deletedSequences = this.flow
                .getOrigin()
                .sequences.filter((sequence) => !sequenceIds.includes(sequence.id));

            return deletedSequences.map((sequence) => sequence.id);
        },

        handleFieldValiationError() {
            if (!this.flowNameError && !this.flowEventNameError) {
                return;
            }

            const currentRouteName = this.$router.history.current.name;

            const hasErrorTabFlow =
                (currentRouteName === 'sw.flow.create.flow' || currentRouteName === 'sw.flow.detail.flow') &&
                this.flowEventNameError;

            const hasErrorTabGeneral =
                (currentRouteName === 'sw.flow.create.general' || currentRouteName === 'sw.flow.detail.general') &&
                this.flowNameError;

            if (hasErrorTabFlow || hasErrorTabGeneral) {
                return;
            }

            // Navigate to another tab which contains field errors
            if (this.flowId) {
                this.$router.push({
                    name: this.flowNameError ? 'sw.flow.detail.general' : 'sw.flow.detail.flow',
                    params: { flowId: this.flowId },
                });

                return;
            }

            this.$router.push({
                name: this.flowNameError ? 'sw.flow.create.general' : 'sw.flow.create.flow',
            });
        },

        saveFinish() {
            this.isLoading = false;
            this.isSaveSuccessful = false;
        },

        onLeaveModalClose() {
            this.nextRoute(false);
            this.nextRoute = null;
            this.showLeavePageWarningModal = false;
        },

        onLeaveModalConfirm() {
            this.showLeavePageWarningModal = false;

            this.$nextTick(() => {
                this.nextRoute();
            });
        },

        removeAllSelectors() {
            const newSequences = this.sequences.filter((sequence) => {
                return sequence.ruleId !== null || sequence.actionName !== null;
            });

            Store.get('swFlow').setSequences(newSequences);
        },

        validateEmptySequence() {
            const invalidSequences = this.sequences.reduce((result, sequence) => {
                if (sequence.ruleId === '' || sequence.actionName === '') {
                    return [
                        ...result,
                        sequence.id,
                    ];
                }

                return result;
            }, []);

            Store.get('swFlow').invalidSequences = invalidSequences;

            return invalidSequences;
        },

        getDataForActionDescription() {
            if (!this.sequences) {
                return null;
            }

            const promises = [];
            // eslint-disable-next-line max-len
            const hasSetOrderStateAction = this.sequences.some(
                (sequence) => sequence.actionName === this.flowBuilderService.getActionName('SET_ORDER_STATE'),
            );

            if (hasSetOrderStateAction) {
                // get support information for set order state action.
                promises.push(
                    this.stateMachineStateRepository.search(this.stateMachineStateCriteria).then((data) => {
                        Store.get('swFlow').stateMachineState = data;
                    }),
                );
            }

            // eslint-disable-next-line max-len
            const hasDocumentAction = this.sequences.some(
                (sequence) => sequence.actionName === this.flowBuilderService.getActionName('GENERATE_DOCUMENT'),
            );

            if (hasDocumentAction) {
                // get support information for generate document action.
                promises.push(
                    this.documentTypeRepository.search(this.documentTypeCriteria).then((data) => {
                        Shopware.Store.get('swFlow').documentTypes = data;
                    }),
                );
            }

            // eslint-disable-next-line max-len
            const hasMailSendAction = this.sequences.some(
                (sequence) => sequence.actionName === this.flowBuilderService.getActionName('MAIL_SEND'),
            );

            if (hasMailSendAction) {
                // get support information for mail send action.
                promises.push(
                    this.mailTemplateRepository.search(this.mailTemplateIdsCriteria).then((data) => {
                        Shopware.Store.get('swFlow').mailTemplates = data;
                    }),
                );
            }

            // eslint-disable-next-line max-len
            const hasChangeCustomerGroup = this.sequences.some(
                (sequence) => sequence.actionName === this.flowBuilderService.getActionName('CHANGE_CUSTOMER_GROUP'),
            );

            if (hasChangeCustomerGroup) {
                // get support information for change customer group action.
                promises.push(
                    this.customerGroupRepository.search(this.customerGroupCriteria).then((data) => {
                        Shopware.Store.get('swFlow').customerGroups = data;
                    }),
                );
            }

            const customFieldActionConstants = [
                this.flowBuilderService.getActionName('SET_ORDER_CUSTOM_FIELD'),
                this.flowBuilderService.getActionName('SET_CUSTOMER_CUSTOM_FIELD'),
                this.flowBuilderService.getActionName('SET_CUSTOMER_GROUP_CUSTOM_FIELD'),
            ];
            // eslint-disable-next-line max-len
            const hasSetCustomFieldAction = this.sequences.some((sequence) =>
                customFieldActionConstants.includes(sequence.actionName),
            );

            if (hasSetCustomFieldAction) {
                promises.push(
                    this.customFieldSetRepository.search(this.customFieldSetCriteria).then((data) => {
                        Shopware.Store.get('swFlow').customFieldSets = data;
                    }),
                );

                promises.push(
                    this.customFieldRepository.search(this.customFieldCriteria).then((data) => {
                        Shopware.Store.get('swFlow').customFields = data;
                    }),
                );
            }

            return Promise.all(promises);
        },

        createFromFlowTemplate() {
            const flow = this.flowRepository.create();
            flow.id = Utils.createId();
            flow.priority = 0;

            return this.flowTemplateRepository
                .get(this.$route.params.flowTemplateId, Context.api, this.flowTemplateCriteria)
                .then((data) => {
                    flow.name = data.name;
                    flow.eventName = data.config?.eventName;
                    flow.description = data.config?.description;
                    flow.sequences = this.buildSequencesFromConfig(data.config?.sequences ?? []);

                    Store.get('swFlow').setFlow(flow);
                    Store.get('swFlow').setOriginFlow(cloneDeep(flow));
                    this.getDataForActionDescription();
                    this.getRuleDataForFlowTemplate();
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

        createSequenceEntity(flowSequence) {
            const entity = this.flowSequenceRepository.create();

            Object.keys(flowSequence).forEach((key) => {
                if (key === 'trueCase') {
                    entity[key] = Boolean(flowSequence[key]);

                    return;
                }

                if (key === 'config') {
                    entity[key] = { ...flowSequence[key] };

                    return;
                }

                entity[key] = flowSequence[key];
            });

            return entity;
        },

        buildSequencesFromConfig(sequences) {
            const parentIds = {};

            sequences = sequences.map((sequence) => {
                sequence = this.createSequenceEntity(sequence);

                parentIds[sequence.id] = Utils.createId();
                sequence.id = parentIds[sequence.id];

                return sequence;
            });

            // update parentId of sequence
            for (let i = 0; i < sequences.length; i += 1) {
                if (sequences[i].parentId !== null) {
                    sequences[i].parentId = parentIds[sequences[i].parentId];
                }
            }

            sequences = Service('flowBuilderService').rearrangeArrayObjects(sequences);

            return new EntityCollection(
                this.flowSequenceRepository.source,
                this.flowSequenceRepository.entityName,
                Context.api,
                null,
                sequences,
            );
        },

        getRuleDataForFlowTemplate() {
            const ruleIds = this.sequences.filter((sequence) => sequence.ruleId !== null).map((sequence) => sequence.ruleId);

            if (!ruleIds.length) {
                return;
            }

            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equalsAny('id', ruleIds));

            this.ruleRepository.search(criteria).then((rules) => {
                const sequencesWithRules = this.sequences.map((sequence) => {
                    if (sequence.ruleId) {
                        sequence.rule = rules.find((item) => item.id === sequence.ruleId);
                    }

                    return sequence;
                });

                Store.get('swFlow').setSequences(sequencesWithRules);
                Store.get('swFlow').setOriginFlow(cloneDeep(this.flow));
            });
        },
    },
};
