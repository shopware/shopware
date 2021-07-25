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

        setInvalidSequences(state, invalidSequences) {
            state.invalidSequences = invalidSequences;
        },
    },

    getters: {
        sequences(state) {
            return state.flow.sequences;
        },

        availableActions(state) {
            if (!state.triggerEvent || !state.triggerActions) return [];

            const activeActions = [];
            Object.entries(state.triggerEvent).forEach(([key, value]) => {
                if (value === true) {
                    activeActions.push(key);
                }
            });

            const availableAction = [];

            state.triggerActions.forEach((action) => {
                if (Array.isArray(action.requirements)) {
                    availableAction.push(action.name);
                    return;
                }

                const keys = Object.keys(action.requirements);
                // check if the current active action contains any required keys from an action option.
                const isActive = activeActions.some(item => keys.includes(item));

                if (isActive) {
                    availableAction.push(action.name);
                }
            });

            return availableAction;
        },
    },
};
