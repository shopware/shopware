import type { ACTION } from '../constant/flow.constant';

/**
 * @sw-package after-sales
 */
const { Service } = Shopware;
const { EntityCollection } = Shopware.Data;
const { types } = Shopware.Utils;

type Flow = Entity<'flow'>;
type Sequence = Entity<'flow_sequence'>;
type Sequences = EntityCollection<'flow_sequence'>;
type Actions = EntityCollection<'app_flow_action'>;
type Event = Entity<'app_flow_event'>;
type Events = EntityCollection<'app_flow_event'>;

type EntityActions =
    | 'ADD_ORDER_TAG'
    | 'REMOVE_ORDER_TAG'
    | 'ADD_CUSTOMER_TAG'
    | 'SET_ORDER_CUSTOM_FIELD'
    | 'REMOVE_CUSTOMER_TAG'
    | 'SET_CUSTOMER_CUSTOM_FIELD'
    | 'ADD_ORDER_AFFILIATE_AND_CAMPAIGN_CODE'
    | 'SET_CUSTOMER_GROUP_CUSTOM_FIELD'
    | 'ADD_CUSTOMER_AFFILIATE_AND_CAMPAIGN_CODE';

type EntityActionName = (typeof ACTION)[EntityActions];

const swFlowStore = Shopware.Store.register('swFlow', {
    state: () => ({
        flow: {
            eventName: '',
            sequences: [] as unknown as Sequences,
        } as Flow,
        originFlow: {} as Flow,
        triggerEvent: {} as Event,
        triggerEvents: [] as unknown as Events,
        triggerActions: [] as unknown as Actions,
        invalidSequences: [],
        stateMachineState: [],
        documentTypes: [],
        mailTemplates: [],
        customFieldSets: [],
        customFields: [],
        customerGroups: [],
        restrictedRules: [],
        appActions: [] as unknown as Actions,
        originAvailableActions: [] as string[],
    }),

    getters: {
        getSelectedAppAction(state) {
            return (actionName: string) => {
                return state.appActions?.find((item) => item.name === actionName);
            };
        },

        sequences(state) {
            return state.flow.sequences;
        },

        hasFlowChanged(state) {
            const flow = {
                ...state.flow,
                sequences: Array.from(state.flow.sequences as EntityCollection<'flow_sequence'>).filter((item) => {
                    if (item.actionName || item.ruleId) {
                        return Object.assign(item, {});
                    }

                    return false;
                }),
            };

            return !types.isEqual(state.originFlow, flow);
        },

        isSequenceEmpty(state) {
            if (!state.flow.sequences?.length) {
                return true;
            }

            const firstSequence = state.flow.sequences[0];
            return !firstSequence.actionName && !firstSequence.ruleId;
        },

        availableActions(state) {
            if (!state.triggerEvent || !state.triggerActions) return [];

            const availableActions: string[] = [];

            state.triggerActions.forEach((action) => {
                if (!action.requirements?.length) {
                    availableActions.push(action.name);
                    return;
                }

                // check if the current active action contains any required keys from an action option.
                const isActive = action.requirements.some((item) => state.triggerEvent?.aware?.includes(item));

                if (!isActive) {
                    return;
                }

                const actionType = Service('flowBuilderService').mapActionType(action.name as EntityActionName);

                if (actionType) {
                    // check if the action is already in the available actions list by typeq
                    const hasDuplicateAction = availableActions.find(
                        (option) => Service('flowBuilderService').mapActionType(option as EntityActionName) === actionType,
                    );

                    if (hasDuplicateAction !== undefined) {
                        return;
                    }
                }

                availableActions.push(action.name);
            });

            return availableActions;
        },

        mailTemplateIds(state) {
            return (
                state.flow.sequences
                    ?.filter((item) => item.actionName === Service('flowBuilderService').getActionName('MAIL_SEND'))
                    .map((item: Sequence) => (item.config as { mailTemplateId?: string })?.mailTemplateId) ?? []
            );
        },

        customFieldSetIds(state) {
            const service = Service('flowBuilderService');
            return (
                state.flow.sequences
                    ?.filter(
                        (item) =>
                            item.actionName === service.getActionName('SET_CUSTOMER_CUSTOM_FIELD') ||
                            item.actionName === service.getActionName('SET_ORDER_CUSTOM_FIELD') ||
                            item.actionName === service.getActionName('SET_CUSTOMER_GROUP_CUSTOM_FIELD'),
                    )
                    .map((item) => (item.config as { customFieldSetId?: string })?.customFieldSetId) ?? []
            );
        },

        customFieldIds(state) {
            const service = Service('flowBuilderService');
            return (
                state.flow.sequences
                    ?.filter(
                        (item) =>
                            item.actionName === service.getActionName('SET_CUSTOMER_CUSTOM_FIELD') ||
                            item.actionName === service.getActionName('SET_ORDER_CUSTOM_FIELD') ||
                            item.actionName === service.getActionName('SET_CUSTOMER_GROUP_CUSTOM_FIELD'),
                    )
                    .map((item) => (item.config as { customFieldId?: string })?.customFieldId) ?? []
            );
        },

        actionGroups() {
            return Service('flowBuilderService').getGroups();
        },

        hasAvailableAction: (state) => (actionName: string) => {
            // This information was originally persisted into the state in the `availableActions` getter.
            // That's an antipattern and caused endless loops in the flow module.
            // Therefore, we need to recalculate the available actions here.
            const getOriginActions = () => {
                const originAvailableActions: string[] = [];

                if (!state.triggerEvent || !state.triggerActions) return [];

                state.triggerActions.forEach((action) => {
                    if (!action.requirements?.length) {
                        originAvailableActions.push(action.name);
                        return;
                    }

                    // check if the current active action contains any required keys from an action option.
                    const isActive = action.requirements.some((item) => state.triggerEvent?.aware?.includes(item));

                    if (!isActive || originAvailableActions.includes(action.name)) {
                        return;
                    }

                    originAvailableActions.push(action.name);
                });

                return originAvailableActions;
            };
            const originAvailableActions = getOriginActions();

            return originAvailableActions?.some((name) => name === actionName) ?? false;
        },
    },

    actions: {
        setAppActions(actions: Actions) {
            this.appActions.push(...actions);
        },

        setFlow(flow: Flow & { config?: Flow }) {
            this.flow = flow;
            if (flow.config) {
                this.flow.description = flow.config.description;
                this.flow.sequences = flow.config.sequences;
                this.flow.eventName = flow.config.eventName;
            }
        },

        setOriginFlow(flow: Flow) {
            this.originFlow = {
                ...flow,
                sequences: flow.sequences?.map((item) => ({ ...item })) as Sequences,
            } as Flow;
        },

        setEventName(eventName: string) {
            this.flow.eventName = eventName;
        },

        setSequences(sequences: Sequences) {
            this.flow.sequences = sequences;
        },

        addSequence(sequence: Sequence) {
            if (this.flow.sequences instanceof EntityCollection) {
                this.flow.sequences.add(sequence);
                return;
            }

            (this.flow.sequences as Sequence[])?.push(sequence);
        },

        removeSequences(sequenceIds: string[]) {
            sequenceIds.forEach((sequenceId) => {
                if (this.flow.sequences instanceof EntityCollection) {
                    this.flow.sequences?.remove(sequenceId);
                } else {
                    this.flow.sequences = (this.flow.sequences as Sequence[])?.filter(
                        (sequence) => sequence.id !== sequenceId,
                    ) as Sequences;
                }
            });
        },

        updateSequence(params: Partial<Sequence> & { id: string }) {
            const sequences = this.flow.sequences;
            if (!sequences) return;

            const sequenceIndex = sequences.findIndex((el) => el.id === params.id);
            const sequence = sequences[sequenceIndex];
            if (!sequence) return;

            const updatedSequence = Object.assign(sequence, params);

            this.flow.sequences = new EntityCollection(sequences.source, sequences.entity, Shopware.Context.api, null, [
                ...sequences.slice(0, sequenceIndex),
                updatedSequence,
                ...sequences.slice(sequenceIndex + 1),
            ]);
        },

        removeCurrentFlow() {
            this.flow = {
                eventName: '',
                sequences: [] as unknown as Sequences,
            } as Flow;
        },

        removeInvalidSequences() {
            this.invalidSequences = [];
        },

        removeTriggerEvent() {
            this.triggerEvent = {} as Event;
        },

        resetFlowState() {
            this.removeCurrentFlow();
            this.removeInvalidSequences();
            this.removeTriggerEvent();
        },

        setRestrictedRules(id: string) {
            void Service('ruleConditionDataProviderService')
                .getRestrictedRules(`flowTrigger.${id}`)
                .then((result) => {
                    this.setRestrictedRules(result?.[0]);
                });
        },

        fetchTriggerActions() {
            void Service('businessEventService')
                .getBusinessEvents()
                .then((result: Events) => {
                    this.triggerEvents = result;
                })
                .catch(() => {
                    this.triggerEvents = [] as unknown as Events;
                });
        },
    },
});

/**
 * @private
 */
export default swFlowStore;

/**
 * @private
 */
export type SwFlowStore = ReturnType<typeof swFlowStore>;
