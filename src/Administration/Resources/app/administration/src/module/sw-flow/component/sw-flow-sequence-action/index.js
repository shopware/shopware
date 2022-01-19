import template from './sw-flow-sequence-action.html.twig';
import './sw-flow-sequence-action.scss';
import { ACTION } from '../../constant/flow.constant';

const { Component, State, Mixin } = Shopware;
const utils = Shopware.Utils;
const { cloneDeep } = utils.object;
const { ShopwareError } = Shopware.Classes;
const { mapState, mapGetters } = Component.getComponentHelper();
const { snakeCase } = utils.string;

Component.register('sw-flow-sequence-action', {
    template,

    inject: ['repositoryFactory', 'flowBuilderService', 'feature'],

    mixins: [
        Mixin.getByName('sw-inline-snippet'),
    ],

    props: {
        sequence: {
            type: Object,
            required: true,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            showAddButton: true,
            fieldError: null,
            selectedAction: '',
            currentSequence: {},
        };
    },

    computed: {
        sequenceRepository() {
            return this.repositoryFactory.create('flow_sequence');
        },

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        actionOptions() {
            return this.availableActions.map((action) => {
                return this.getActionTitle(action);
            });
        },

        sequenceData() {
            if (this.sequence.id) {
                return [
                    {
                        ...this.sequence,
                        ...this.getActionTitle(this.sequence.actionName),
                    },
                ];
            }

            return this.sortByPosition(Object.values(this.sequence).map(item => {
                return {
                    ...item,
                    ...this.getActionTitle(item.actionName),
                };
            }));
        },

        showAddAction() {
            return !(
                this.sequence.actionName === ACTION.STOP_FLOW ||
                this.sequenceData.some(sequence => sequence.actionName === ACTION.STOP_FLOW)
            );
        },

        actionClasses() {
            return {
                'is--stop-flow': !this.showAddAction,
            };
        },

        modalName() {
            return this.flowBuilderService.getActionModalName(this.selectedAction);
        },

        actionDescription() {
            return {
                [ACTION.STOP_FLOW]: () => this.$tc('sw-flow.actions.textStopFlowDescription'),
                [ACTION.SET_ORDER_STATE]: (config) => this.getSetOrderStateDescription(config),
                [ACTION.GENERATE_DOCUMENT]: (config) => this.getGenerateDocumentDescription(config),
                [ACTION.MAIL_SEND]: (config) => this.getMailSendDescription(config),
                [ACTION.CHANGE_CUSTOMER_GROUP]: (config) => this.getCustomerGroupDescription(config),
                [ACTION.CHANGE_CUSTOMER_STATUS]: (config) => this.getCustomerStatusDescription(config),
                [ACTION.SET_CUSTOMER_CUSTOM_FIELD]: (config) => this.getCustomFieldDescription(config),
                // eslint-disable-next-line max-len
                [ACTION.SET_CUSTOMER_GROUP_CUSTOM_FIELD]: (config) => this.getCustomFieldDescription(config),
                [ACTION.SET_ORDER_CUSTOM_FIELD]: (config) => this.getCustomFieldDescription(config),
                [ACTION.ADD_CUSTOMER_AFFILIATE_AND_CAMPAIGN_CODE]:
                    (config) => this.getAffiliateAndCampaignCodeDescription(config),
                [ACTION.ADD_ORDER_AFFILIATE_AND_CAMPAIGN_CODE]:
                    (config) => this.getAffiliateAndCampaignCodeDescription(config),
            };
        },

        ...mapState('swFlowState',
            [
                'invalidSequences',
                'stateMachineState',
                'documentTypes',
                'mailTemplates',
                'customerGroups',
                'customFieldSets',
                'customFields',
            ]),
        ...mapGetters('swFlowState', ['availableActions']),
    },

    watch: {
        sequence: {
            handler() {
                this.setFieldError();
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.showAddButton = this.sequenceData.length > 1 || !!this.sequence?.actionName;
        },

        openDynamicModal(value) {
            if (value === ACTION.STOP_FLOW) {
                this.addAction({
                    name: ACTION.STOP_FLOW,
                    config: null,
                });
                return;
            }
            this.selectedAction = value;
        },

        onSaveActionSuccess(sequence) {
            const { config, id } = sequence;
            let entity = config?.entity;
            let actionName = this.selectedAction;

            const actionType = this.flowBuilderService.mapActionType(this.selectedAction);
            if (actionType && entity) {
                entity = snakeCase(entity).replace('_', '.');
                actionName = actionType.replace('entity', entity);
            }

            if (!id) {
                this.addAction({
                    name: actionName,
                    config: config,
                });
            } else {
                this.editAction({
                    name: actionName,
                    config: config,
                });
            }

            this.onCloseModal();
        },

        onCloseModal() {
            this.currentSequence = {};
            this.selectedAction = '';
        },

        addAction(action) {
            if (!action.name) {
                return;
            }

            if (!this.sequence.actionName && this.sequence.id) {
                State.commit('swFlowState/updateSequence', {
                    id: this.sequence.id,
                    actionName: action.name,
                    config: action.config,
                });
            } else {
                const lastSequence = this.sequenceData[this.sequenceData.length - 1];

                let sequence = this.sequenceRepository.create();
                const newSequence = {
                    ...sequence,
                    parentId: lastSequence.parentId,
                    trueCase: lastSequence.trueCase,
                    displayGroup: lastSequence.displayGroup,
                    ruleId: null,
                    actionName: action.name,
                    position: lastSequence.position + 1,
                    config: action.config,
                    id: utils.createId(),
                };

                sequence = Object.assign(sequence, newSequence);
                State.commit('swFlowState/addSequence', sequence);
            }

            this.removeFieldError();
            this.toggleAddButton();
        },

        editAction(action) {
            if (!action.name) {
                return;
            }

            State.commit('swFlowState/updateSequence', {
                id: this.currentSequence.id,
                actionName: action.name,
                config: action.config,
            });
        },

        removeAction(id) {
            State.commit('swFlowState/removeSequences', [id]);
        },

        actionsWithoutStopFlow() {
            // When action list only has 1 item, this.sequence has object type
            if (this.sequence.id) {
                return [{
                    ...this.sequence,
                }];
            }

            const sequences = Object.values(this.sequence);
            return this.sortByPosition(sequences.filter(sequence => sequence.actionName !== ACTION.STOP_FLOW));
        },

        showMoveOption(action, type) {
            const actions = this.actionsWithoutStopFlow();
            if (actions.length <= 1) return false;
            if (type === 'up' && actions[0].position === action.position) return false;
            if (type === 'down' && actions[actions.length - 1].position === action.position) return false;

            return action.actionName !== ACTION.STOP_FLOW;
        },

        moveAction(action, type) {
            const actions = this.actionsWithoutStopFlow();
            const currentIndex = actions.findIndex(item => item.position === action.position);
            const moveAction = type === 'up' ? actions[currentIndex - 1] : actions[currentIndex + 1];
            const moveActionClone = cloneDeep(moveAction);

            State.commit('swFlowState/updateSequence', { id: moveAction.id, position: action.position });
            State.commit('swFlowState/updateSequence', { id: action.id, position: moveActionClone.position });
        },

        onEditAction(sequence) {
            if (!sequence?.actionName) {
                return;
            }

            this.currentSequence = sequence;
            this.selectedAction = sequence.actionName;
        },

        removeActionContainer() {
            const removeSequences = this.sequence.id ? [this.sequence.id] : Object.keys(this.sequence);

            State.commit('swFlowState/removeSequences', removeSequences);
        },

        getActionTitle(actionName) {
            if (!actionName) {
                return null;
            }

            const actionTitle = this.flowBuilderService.getActionTitle(actionName);
            return {
                ...actionTitle,
                label: this.$tc(actionTitle.label),
            };
        },

        toggleAddButton() {
            this.showAddButton = !this.showAddButton;
        },

        sortByPosition(sequences) {
            return sequences.sort((prev, current) => {
                return prev.position - current.position;
            });
        },

        stopFlowStyle(value) {
            return {
                'is--stop-flow': value === ACTION.STOP_FLOW,
            };
        },

        convertTagString(tagsString) {
            return tagsString.toString().replace(/,/g, ', ');
        },

        getActionDescription(sequence) {
            const { actionName, config } = sequence;

            if (!actionName) return '';

            if (actionName.includes('tag') &&
                (actionName.includes('add') || actionName.includes('remove'))) {
                return `${this.$tc('sw-flow.actions.labelTo', 0, {
                    entity: config.entity,
                })}<br>${this.$tc('sw-flow.actions.labelTag', 0, {
                    tagNames: this.convertTagString(Object.values(config.tagIds)),
                })}`;
            }

            if (typeof this.actionDescription[actionName] !== 'function') {
                return '';
            }

            return this.actionDescription[actionName](config);
        },

        setFieldError() {
            if (!this.invalidSequences?.includes(this.sequence.id)) {
                this.fieldError = null;
                return;
            }

            this.fieldError = new ShopwareError({
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            });
        },

        removeFieldError() {
            if (!this.fieldError) {
                return;
            }

            this.fieldError = null;
            const invalidSequences = this.invalidSequences?.filter(id => this.sequence.id !== id);
            State.commit('swFlowState/setInvalidSequences', invalidSequences);
        },

        isNotStopFlow(item) {
            return item.actionName !== ACTION.STOP_FLOW;
        },

        getSetOrderStateDescription(config) {
            const description = [];
            if (config.order) {
                const orderStatus = this.stateMachineState.find(item => item.technicalName === config.order);
                const orderStatusName = orderStatus?.translated?.name || '';
                description.push(`${this.$tc('sw-flow.modals.status.labelOrderStatus')}: ${orderStatusName}`);
            }

            if (config.order_delivery) {
                const deliveryStatus = this.stateMachineState.find(item => item.technicalName === config.order_delivery);
                const deliveryStatusName = deliveryStatus?.translated?.name || '';
                description.push(`${this.$tc('sw-flow.modals.status.labelDeliveryStatus')}: ${deliveryStatusName}`);
            }

            if (config.order_transaction) {
                const paymentStatus = this.stateMachineState.find(item => item.technicalName === config.order_transaction);
                const paymentStatusName = paymentStatus?.translated?.name || '';
                description.push(`${this.$tc('sw-flow.modals.status.labelPaymentStatus')}: ${paymentStatusName}`);
            }

            return description.join('<br>');
        },

        getGenerateDocumentDescription(config) {
            if (config.documentType) {
                config = {
                    documentTypes: [config],
                };
            }

            const documentType = config.documentTypes.map((type) => {
                return this.documentTypes.find(item => item.technicalName === type.documentType)?.translated?.name;
            });

            return this.$tc('sw-flow.modals.document.documentDescription', 0, {
                documentType: this.convertTagString(documentType),
            });
        },

        getCustomerGroupDescription(config) {
            const customerGroup = this.customerGroups.find(item => item.id === config.customerGroupId);
            return `${this.$tc('sw-flow.modals.customerGroup.customerGroupDescription', 0, {
                customerGroup: customerGroup?.translated?.name,
            })}`;
        },

        getCustomerStatusDescription(config) {
            return config.active
                ? this.$tc('sw-flow.modals.customerStatus.customerStatusDescriptionActive')
                : this.$tc('sw-flow.modals.customerStatus.customerStatusDescriptionInactive');
        },

        getMailSendDescription(config) {
            const mailTemplateData = this.mailTemplates.find(item => item.id === config.mailTemplateId);

            let mailSendDescription = this.$tc('sw-flow.actions.labelTemplate', 0, {
                template: mailTemplateData?.mailTemplateType?.name,
            });

            let mailDescription = mailTemplateData?.description;

            if (mailDescription) {
                // Truncate description string
                mailDescription = mailDescription.length > 30
                    ? `${mailDescription.substring(0, 30)}...`
                    : mailDescription;

                mailSendDescription = `${mailSendDescription}<br>${this.$tc('sw-flow.actions.labelDescription', 0, {
                    description: mailDescription,
                })}`;
            }

            return mailSendDescription;
        },

        getCustomFieldDescription(config) {
            const customFieldSet = this.customFieldSets.find(item => item.id === config.customFieldSetId);
            const customField = this.customFields.find(item => item.id === config.customFieldId);
            if (!customFieldSet || !customField) {
                return '';
            }

            return `${this.$tc('sw-flow.actions.labelCustomFieldSet', 0, {
                customFieldSet: this.getInlineSnippet(customFieldSet.config.label) || customFieldSet.name,
            })}<br>${this.$tc('sw-flow.actions.labelCustomField', 0, {
                customField: this.getInlineSnippet(customField.config.label) || customField.name,
            })}<br>${this.$tc('sw-flow.actions.labelCustomFieldOption', 0, {
                customFieldOption: config.optionLabel,
            })}`;
        },

        getAffiliateAndCampaignCodeDescription(config) {
            let description = this.$tc('sw-flow.actions.labelTo', 0, {
                entity: config.entity,
            });

            if (config.affiliateCode.upsert || config.affiliateCode.value != null) {
                description = `${description}<br>${this.$tc('sw-flow.actions.labelAffiliateCode', 0, {
                    affiliateCode: config.affiliateCode.value ? config.affiliateCode.value : 'null',
                })}`;
            }

            if (config.campaignCode.upsert || config.campaignCode.value != null) {
                description = `${description}<br>${this.$tc('sw-flow.actions.labelCampaignCode', 0, {
                    campaignCode: config.campaignCode.value ? config.campaignCode.value : 'null',
                })}`;
            }

            return description;
        },
    },
});
