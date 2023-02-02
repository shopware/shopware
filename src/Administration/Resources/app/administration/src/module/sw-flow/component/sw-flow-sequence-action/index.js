import orderBy from 'lodash/orderBy';
import sortBy from 'lodash/sortBy';
import template from './sw-flow-sequence-action.html.twig';
import './sw-flow-sequence-action.scss';
import { ACTION, ACTION_GROUP, GENERAL_GROUP } from '../../constant/flow.constant';

const { Component, State, Mixin } = Shopware;
const utils = Shopware.Utils;
const { cloneDeep } = utils.object;
const { ShopwareError } = Shopware.Classes;
const { mapState, mapGetters } = Component.getComponentHelper();
const { snakeCase } = utils.string;
const { Criteria } = Shopware.Data;

/**
 * @private
 * @package business-ops
 */
export default {
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
            fieldError: null,
            selectedAction: '',
            currentSequence: {},
            appFlowActions: [],
            isAppAction: false,
        };
    },

    computed: {
        sequenceRepository() {
            return this.repositoryFactory.create('flow_sequence');
        },

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        appFlowActionRepository() {
            return this.repositoryFactory.create('app_flow_action');
        },

        actionOptions() {
            const actions = this.availableActions.map((action) => {
                return this.getActionTitle(action);
            });

            return this.sortActionOptions(actions);
        },

        groups() {
            const groups = this.actionGroups.map(group => {
                return {
                    id: group,
                    label: this.$tc(`sw-flow.actions.group.${group}`),
                };
            });

            if (this.appFlowActions.length) {
                const action = this.appFlowActions[0];
                const appGroup = this.actionGroups.find(group => group === action?.app?.name);
                if (!appGroup) {
                    groups.unshift({
                        id: `${action?.app?.name[0].toLowerCase()}${action?.app?.name.slice(1)}`,
                        label: action?.app?.label,
                    });
                }
            }

            return sortBy(groups, ['label']);
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
                'has--arrow': this.errorArrow,
            };
        },

        errorArrow() {
            return !this.isValidAction(this.sequence) && this.sequence.actionName && this.sequence.trueBlock;
        },

        modalName() {
            if (this.getSelectedAppFlowAction(this.selectedAction)) {
                return 'sw-flow-app-action-modal';
            }

            return this.flowBuilderService.getActionModalName(this.selectedAction);
        },

        currentLocale() {
            return Shopware.State.get('session').currentLocale;
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
                [ACTION.APP_FLOW_ACTION]: (config, actionName) => this.getAppFlowActionDescription(config, actionName),
            };
        },

        ...mapState(
            'swFlowState',
            [
                'invalidSequences',
                'stateMachineState',
                'documentTypes',
                'mailTemplates',
                'customerGroups',
                'customFieldSets',
                'customFields',
                'triggerEvent',
                'triggerActions',
            ],
        ),
        ...mapGetters('swFlowState', ['availableActions', 'actionGroups', 'sequences']),
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
            this.getAppFlowAction();
        },

        openDynamicModal(value) {
            const appAction = this.getSelectedAppFlowAction(value);
            if (appAction) {
                this.isAppAction = true;
                this.currentSequence.propsAppFlowAction = appAction;
            }

            if (value === ACTION.STOP_FLOW) {
                this.addAction({
                    name: ACTION.STOP_FLOW,
                    config: null,
                });
                return;
            }
            this.selectedAction = value;
        },

        getSelectedAppFlowAction(actionName) {
            return this.appFlowActions.find((item) => item.name === actionName);
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
            this.isAppAction = false;
            this.$delete(this.sequence, 'propsAppFlowAction');
        },

        addAction(action) {
            if (!action.name) {
                return;
            }

            const appAction = this.getSelectedAppFlowAction(action.name);

            if (!this.sequence.actionName && this.sequence.id) {
                const data = {
                    id: this.sequence.id,
                    actionName: action.name,
                    config: action.config,
                };

                if (appAction) {
                    data.appFlowActionId = appAction.id;
                }

                State.commit('swFlowState/updateSequence', data);
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

                if (appAction) {
                    newSequence.appFlowActionId = appAction.id;
                }

                sequence = Object.assign(sequence, newSequence);
                State.commit('swFlowState/addSequence', sequence);
            }

            this.removeFieldError();
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
            const action = this.sequences.find(sequence => sequence.id === id);
            if (action?.id) {
                const sequencesInGroup = this.sequences.filter(item => item.parentId === action.parentId
                    && item.trueCase === action.trueCase
                    && item.id !== id);

                sequencesInGroup.forEach((item, index) => {
                    State.commit('swFlowState/updateSequence', {
                        id: item.id,
                        position: index + 1,
                    });
                });
            }

            if (this.isAppDisabled(this.getSelectedAppFlowAction(this.sequence[id]?.actionName))) return;

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
            if (this.isAppDisabled(this.getSelectedAppFlowAction(action.actionName))) return;

            const actions = this.actionsWithoutStopFlow();
            const currentIndex = actions.findIndex(item => item.position === action.position);
            const moveAction = type === 'up' ? actions[currentIndex - 1] : actions[currentIndex + 1];
            const moveActionClone = cloneDeep(moveAction);

            State.commit('swFlowState/updateSequence', { id: moveAction.id, position: action.position });
            State.commit('swFlowState/updateSequence', { id: action.id, position: moveActionClone.position });
        },

        onEditAction(sequence, target, key) {
            if (sequence.actionName && sequence.actionName === ACTION.STOP_FLOW) {
                return;
            }

            if (!this.$refs.contextButton[key]) {
                return;
            }

            if (!sequence?.actionName || !target) {
                return;
            }

            if (this.$refs.contextButton[key].$el.contains(target)) {
                return;
            }

            if (this.isAppDisabled(this.getSelectedAppFlowAction(sequence.actionName))) return;

            sequence.propsAppFlowAction = this.getSelectedAppFlowAction(sequence.actionName);
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

            const appAction = this.getSelectedAppFlowAction(actionName);
            if (appAction) {
                return {
                    label: appAction.label || appAction.translated?.label,
                    icon: appAction.swIcon,
                    iconRaw: appAction.icon,
                    value: appAction.name,
                    disabled: !appAction.app?.active,
                    group: `${appAction.app?.name[0].toLowerCase()}${appAction.app?.name.slice(1)}`,
                };
            }

            const actionTitle = this.flowBuilderService.getActionTitle(actionName);
            return {
                ...actionTitle,
                label: this.$tc(actionTitle.label),
                group: ACTION_GROUP[actionName] || GENERAL_GROUP,
            };
        },

        getAppFlowAction() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('app');
            return this.appFlowActionRepository.search(criteria, Shopware.Context.api).then((response) => {
                this.appFlowActions = response;
            });
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

            if (this.getSelectedAppFlowAction(actionName)) {
                return this.actionDescription[ACTION.APP_FLOW_ACTION](config, actionName);
            }

            if (actionName.includes('tag') &&
                (actionName.includes('add') || actionName.includes('remove'))) {
                return `${this.$tc('sw-flow.actions.labelTo', 0, {
                    entity: this.capitalize(config.entity),
                })}<br>${this.$tc('sw-flow.actions.labelTag', 0, {
                    tagNames: this.convertTagString(Object.values(config.tagIds)),
                })}`;
            }

            if (typeof this.actionDescription[actionName] !== 'function' && !this.isAppAction) {
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
                const orderStatus = this.stateMachineState.find(item => item.technicalName === config.order
                && item.stateMachine.technicalName === 'order.state');
                const orderStatusName = orderStatus?.translated?.name || '';
                description.push(`${this.$tc('sw-flow.modals.status.labelOrderStatus')}: ${orderStatusName}`);
            }

            if (config.order_delivery) {
                const deliveryStatus = this.stateMachineState.find(item => item.technicalName === config.order_delivery
                    && item.stateMachine.technicalName === 'order_delivery.state');
                const deliveryStatusName = deliveryStatus?.translated?.name || '';
                description.push(`${this.$tc('sw-flow.modals.status.labelDeliveryStatus')}: ${deliveryStatusName}`);
            }

            if (config.order_transaction) {
                const paymentStatus = this.stateMachineState.find(item => item.technicalName === config.order_transaction
                    && item.stateMachine.technicalName === 'order_transaction.state');
                const paymentStatusName = paymentStatus?.translated?.name || '';
                description.push(`${this.$tc('sw-flow.modals.status.labelPaymentStatus')}: ${paymentStatusName}`);
            }

            const forceTransition = config.force_transition
                ? this.$tc('global.default.yes')
                : this.$tc('global.default.no');

            description.push(`${this.$tc('sw-flow.modals.status.forceTransition')}: ${forceTransition}`);

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

            return this.convertTagString(documentType);
        },

        getCustomerGroupDescription(config) {
            const customerGroup = this.customerGroups.find(item => item.id === config.customerGroupId);
            return customerGroup?.translated?.name;
        },

        getCustomerStatusDescription(config) {
            return config.active
                ? this.$tc('sw-flow.modals.customerStatus.active')
                : this.$tc('sw-flow.modals.customerStatus.inactive');
        },

        getMailSendDescription(config) {
            const mailTemplateData = this.mailTemplates.find(item => item.id === config.mailTemplateId);

            let mailSendDescription = this.$tc('sw-flow.actions.labelTemplate', 0, {
                template: mailTemplateData?.mailTemplateType?.name,
            });

            let mailDescription = mailTemplateData?.description;

            if (mailDescription) {
                // Truncate description string
                mailDescription = mailDescription.length > 60
                    ? `${mailDescription.substring(0, 60)}...`
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
                entity: this.capitalize(config.entity),
            });

            if (config.affiliateCode.upsert || config.affiliateCode.value != null) {
                description = `${description}<br>${this.$tc('sw-flow.actions.labelAffiliateCode', 0, {
                    affiliateCode: config.affiliateCode.value || '',
                })}`;
            }

            if (config.campaignCode.upsert || config.campaignCode.value != null) {
                description = `${description}<br>${this.$tc('sw-flow.actions.labelCampaignCode', 0, {
                    campaignCode: config.campaignCode.value || '',
                })}`;
            }

            return description;
        },

        capitalize(msg) {
            return `${msg.slice(0, 1).toUpperCase()}${msg.slice(1)}`;
        },

        getAppFlowActionDescription(config, actionName) {
            const cloneConfig = { ...config };
            let descriptions = '';

            Object.entries(cloneConfig).forEach(([fieldName]) => {
                if (typeof cloneConfig[fieldName] === 'object' && cloneConfig[fieldName].length > 1) {
                    let html = '';
                    cloneConfig[fieldName].forEach((val) => {
                        const valPreview = this.formatValuePreview(fieldName, actionName, val);
                        html = `${html}- ${valPreview}<br/>`;
                    });

                    descriptions = `${descriptions}${this.convertLabelPreview(fieldName, actionName)}:<br/> ${html}`;

                    return;
                }

                const valPreview = this.formatValuePreview(fieldName, actionName, cloneConfig[fieldName]);
                descriptions = `${descriptions}${this.convertLabelPreview(fieldName, actionName)}: ${valPreview}<br/>`;
            });

            return descriptions;
        },

        formatValuePreview(fieldName, actionName, val) {
            const appAction = this.getSelectedAppFlowAction(actionName);
            if (appAction === undefined) {
                return val;
            }

            const config = appAction.config.find((field) => field.name === fieldName);
            if (config === undefined) {
                return val;
            }

            if (['password'].includes(config.type)) {
                return val.replace(/([^;])/g, '*');
            }

            if (['single-select', 'multi-select'].includes(config.type)) {
                const value = typeof val === 'string' ? val : val[0];
                const option = config.options.find((opt) => opt.value === value);
                if (option === undefined) {
                    return val;
                }

                return option.label[this.currentLocale] ?? config.label['en-GB'] ?? val;
            }

            if (['datetime', 'date', 'time'].includes(config.type)) {
                return new Date(val);
            }

            if (['colorpicker'].includes(config.type)) {
                return `<span class="sw-color-badge is--default" style="background: ${val};"></span> ${val}`;
            }

            return val;
        },

        convertLabelPreview(fieldName, actionName) {
            const appAction = this.getSelectedAppFlowAction(actionName);
            if (appAction === undefined) {
                return fieldName;
            }

            const config = appAction.config.find((field) => field.name === fieldName);
            if (config === undefined) {
                return fieldName;
            }

            return config.label[this.currentLocale] ?? config.label['en-GB'] ?? fieldName;
        },

        isAppDisabled(appAction) {
            if (!appAction) return false;
            return !appAction.app.active;
        },

        getStopFlowIndex(actions) {
            const indexes = actions.map((item, index) => {
                if (item.group === GENERAL_GROUP) {
                    return index;
                }

                return false;
            }).filter(item => item > 0);

            return indexes.pop() || actions.length;
        },

        sortActionOptions(actions) {
            const stopAction = actions.pop();
            actions = orderBy(actions, ['group', 'label']);

            actions.forEach((action) => {
                if (action.group && action.group !== GENERAL_GROUP) return;

                action.group = action.group || GENERAL_GROUP;

                actions.push(actions.splice(actions.findIndex(el => el.group === GENERAL_GROUP), 1)[0]);
            });

            actions = sortBy(actions, ['group', 'label'], ['esc', 'esc']);
            const stopFlowIndex = this.getStopFlowIndex(actions) + 1;
            actions.splice(stopFlowIndex, 0, stopAction);

            return actions;
        },

        isValidAction(action) {
            if (!this.triggerActions.length || !action.actionName) {
                return true;
            }

            return this.triggerActions.find(item => item.name === action.actionName);
        },
    },
};
