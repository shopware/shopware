import { ACTION } from '../constant/flow.constant';

const { EntityCollection } = Shopware.Data;

export default {
    namespaced: true,

    state: {
        flow: {
            eventName: '',
            sequences: [],
        },
        triggerEvent: {},
        triggerActions: [],
        invalidSequences: [],
        stateMachineState: [],
        documentTypes: [],
        mailTemplates: [],
    },

    mutations: {
        setFlow(state, flow) {
            state.flow = flow;
        },

        setTriggerActions(state, actions) {
            state.triggerActions = actions;
        },

        setTriggerEvent(state, event) {
            state.triggerEvent = event;
        },

        setEventName(state, eventName) {
            state.flow.eventName = eventName;
        },

        setSequences(state, sequences) {
            state.flow.sequences = sequences;
        },

        addSequence(state, sequence) {
            state.flow.sequences.add(sequence);
        },

        removeSequences(state, sequenceIds) {
            sequenceIds.forEach(sequenceId => {
                state.flow.sequences.remove(sequenceId);
            });
        },

        updateSequence(state, params) {
            const sequences = state.flow.sequences;
            const sequenceIndex = sequences.findIndex(el => el.id === params.id);

            let updatedSequence = {
                ...sequences[sequenceIndex],
                ...params,
            };

            updatedSequence = Object.assign(sequences[sequenceIndex], updatedSequence);

            state.flow.sequences = new EntityCollection(
                sequences.source,
                sequences.entity,
                Shopware.Context.api,
                null,
                [
                    ...sequences.slice(0, sequenceIndex),
                    updatedSequence,
                    ...sequences.slice(sequenceIndex + 1),
                ],
            );
        },

        setStateMachineState(state, stateMachineState) {
            state.stateMachineState = stateMachineState;
        },

        setInvalidSequences(state, invalidSequences) {
            state.invalidSequences = invalidSequences;
        },

        setDocumentTypes(state, documentTypes) {
            state.documentTypes = documentTypes;
        },

        setMailTemplates(state, mailTemplates) {
            state.mailTemplates = mailTemplates;
        },

        removeCurrentFlow(state) {
            state.flow = {
                eventName: '',
                sequences: [],
            };
        },

        removeInvalidSequences(state) {
            state.invalidSequences = [];
        },

        removeTriggerEvent(state) {
            state.triggerEvent = {};
        },
    },

    getters: {
        sequences(state) {
            return state.flow.sequences;
        },

        isSequenceEmpty(state) {
            if (!state.flow.sequences.length) {
                return true;
            }

            if (state.flow.sequences.length > 1) {
                return false;
            }

            const firstSequence = state.flow.sequences.first();
            return !firstSequence.actionName && !firstSequence.ruleId;
        },

        availableActions(state) {
            if (!state.triggerEvent || !state.triggerActions) return [];

            const availableAction = [];

            state.triggerActions.forEach((action) => {
                if (!action.requirements.length) {
                    availableAction.push(action.name);
                    return;
                }

                // check if the current active action contains any required keys from an action option.
                const isActive = action.requirements.some(item => state.triggerEvent?.aware?.includes(item));

                if (isActive) {
                    availableAction.push(action.name);
                }
            });

            return availableAction;
        },

        mailTemplateIds(state) {
            return state.flow.sequences
                .filter(item => item.actionName === ACTION.MAIL_SEND)
                .map(item => item.config?.mailTemplateId);
        },
    },

    actions: {
        resetFlowState({ commit }) {
            commit('removeCurrentFlow');
            commit('removeInvalidSequences');
            commit('removeTriggerEvent');
        },
    },
};
