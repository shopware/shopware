const { EntityCollection } = Shopware.Data;

export default {
    namespaced: true,

    state: {
        flow: {
            eventName: '',
            sequences: [],
        },
        invalidSequences: [],
    },

    mutations: {
        setFlow(state, flow) {
            state.flow = flow;
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
    },
};
