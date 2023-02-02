import template from './sw-flow-sequence-action-error.html.twig';
import './sw-flow-sequence-action-error.scss';

const { Component, State } = Shopware;
const { mapGetters } = Component.getComponentHelper();

/**
 * @private
 * @package business-ops
 */
export default {
    template,

    props: {
        sequence: {
            type: Object,
            required: true,
        },
    },

    computed: {
        ...mapGetters('swFlowState', ['sequences']),
    },

    methods: {
        removeWarning(id) {
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

            State.commit('swFlowState/removeSequences', [id]);
        },
    },
};
