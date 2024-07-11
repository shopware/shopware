import orderBy from 'lodash/orderBy';
import sortBy from 'lodash/sortBy';
import template from './sw-flow-sequence-action.html.twig';
import './sw-flow-sequence-action.scss';

const { Component, State, Mixin } = Shopware;
const utils = Shopware.Utils;
const { cloneDeep } = utils.object;
const { ShopwareError } = Shopware.Classes;
const { mapState, mapGetters } = Component.getComponentHelper();
const { snakeCase } = utils.string;

/**
 * @private
 * @package services-settings
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
        isUnknownTrigger: {
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

            if (this.appActions?.length) {
                const action = this.appActions[0];
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
                this.sequence.actionName === this.stopFlowActionName ||
                this.sequenceData.some(sequence => sequence.actionName === this.stopFlowActionName)
            );
        },

        stopFlowActionName() {
            return this.flowBuilderService.getActionName('STOP_FLOW');
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
            if (this.getSelectedAppAction(this.selectedAction)) {
                return 'sw-flow-app-action-modal';
            }

            return this.flowBuilderService.getActionModalName(this.selectedAction);
        },

        currentLocale() {
            return Shopware.State.get('session').currentLocale;
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
        ...mapGetters(
            'swFlowState',
            [
                'availableActions',
                'actionGroups',
                'sequences',
                'appActions',
                'getSelectedAppAction',
                'hasAvailableAction',
            ],
        ),
    },

    watch: {
        sequence: {
            handler() {
                this.setFieldError();
            },
        },
    },

    methods: {
        openDynamicModal(value) {
            const appAction = this.getSelectedAppAction(value);
            if (appAction) {
                this.isAppAction = true;
                this.currentSequence.propsAppFlowAction = appAction;
            }

            if (value === this.stopFlowActionName) {
                this.addAction({
                    name: this.stopFlowActionName,
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
            this.isAppAction = false;
            this.$delete(this.sequence, 'propsAppFlowAction');
        },

        addAction(action) {
            if (!action.name) {
                return;
            }

            const appAction = this.getSelectedAppAction(action.name);

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

            if (this.isAppDisabled(this.getSelectedAppAction(this.sequence[id]?.actionName))) return;

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
            return this.sortByPosition(sequences.filter(sequence => sequence.actionName !== this.stopFlowActionName));
        },

        showMoveOption(action, type) {
            const actions = this.actionsWithoutStopFlow();
            if (actions.length <= 1) return false;
            if (type === 'up' && actions[0].position === action.position) return false;
            if (type === 'down' && actions[actions.length - 1].position === action.position) return false;

            return action.actionName !== this.stopFlowActionName;
        },

        moveAction(action, type, key) {
            if (this.isAppDisabled(this.getSelectedAppAction(action.actionName))) return;

            const actions = this.actionsWithoutStopFlow();
            const currentIndex = actions.findIndex(item => item.position === action.position);
            const moveAction = type === 'up' ? actions[currentIndex - 1] : actions[currentIndex + 1];
            const moveActionClone = cloneDeep(moveAction);

            State.commit('swFlowState/updateSequence', { id: moveAction.id, position: action.position });
            State.commit('swFlowState/updateSequence', { id: action.id, position: moveActionClone.position });

            const index = type === 'up' ? key - 1 : key + 1;
            const contextButtons = this.$refs.contextButton;
            [contextButtons[key], contextButtons[index]] = [contextButtons[index], contextButtons[key]];
        },

        onEditAction(sequence, target, key) {
            if (sequence.actionName && sequence.actionName === this.stopFlowActionName) {
                return;
            }

            if (!this.hasAvailableAction(sequence.actionName)) {
                return;
            }

            if (!sequence?.actionName || !target) {
                return;
            }

            if (this.$refs.contextButton[key] && this.$refs.contextButton[key].$el.contains(target)) {
                return;
            }

            if (this.isAppDisabled(this.getSelectedAppAction(sequence.actionName))) return;

            sequence.propsAppFlowAction = this.getSelectedAppAction(sequence.actionName);
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

            const appAction = this.getSelectedAppAction(actionName);
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
                group: this.flowBuilderService.getActionGroupMapping(actionName),
            };
        },

        sortByPosition(sequences) {
            return sequences.sort((prev, current) => {
                return prev.position - current.position;
            });
        },

        stopFlowStyle(value) {
            return {
                'is--stop-flow': value === this.stopFlowActionName,
            };
        },

        getActionDescriptions(sequence) {
            if (!sequence.actionName) return '';

            const data = {
                appActions: this.appActions,
                customerGroups: this.customerGroups,
                customFieldSets: this.customFieldSets,
                customFields: this.customFields,
                stateMachineState: this.stateMachineState,
                documentTypes: this.documentTypes,
                mailTemplates: this.mailTemplates,
            };

            return this.flowBuilderService.getActionDescriptions(data, sequence, this);
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
            return item.actionName !== this.stopFlowActionName;
        },

        capitalize(msg) {
            return `${msg.slice(0, 1).toUpperCase()}${msg.slice(1)}`;
        },

        isAppDisabled(appAction) {
            if (!appAction) return false;
            return !appAction.app.active;
        },

        getStopFlowIndex(actions) {
            const indexes = actions.map((item, index) => {
                if (item.group === this.flowBuilderService.getGroup('GENERAL')) {
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
                if (action.group && action.group !== this.flowBuilderService.getGroup('GENERAL')) return;

                action.group = action.group || this.flowBuilderService.getGroup('GENERAL');

                // eslint-disable-next-line max-len
                actions.push(actions.splice(actions.findIndex(el => el.group === this.flowBuilderService.getGroup('GENERAL')), 1)[0]);
            });

            actions = sortBy(actions, ['group', 'label'], ['esc', 'esc']);
            const stopFlowIndex = this.getStopFlowIndex(actions) + 1;
            actions.splice(stopFlowIndex, 0, stopAction);

            return actions;
        },

        isValidAction(actionName) {
            return actionName && this.hasAvailableAction(actionName);
        },
    },
};
