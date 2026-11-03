import template from './sw-flow-sequence-action-error.html.twig';
import './sw-flow-sequence-action-error.scss';
import { mapState, useFlowStore } from '../dependencies';

/**
 * @private
 * @sw-package after-sales
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
        ...mapState(() => useFlowStore(), ['sequences']),
    },

    methods: {
        removeWarning(id) {
            const action = this.sequences.find((sequence) => sequence.id === id);
            if (action?.id) {
                const sequencesInGroup = this.sequences.filter(
                    (item) => item.parentId === action.parentId && item.trueCase === action.trueCase && item.id !== id,
                );

                sequencesInGroup.forEach((item, index) => {
                    useFlowStore().updateSequence({
                        id: item.id,
                        position: index + 1,
                    });
                });
            }

            useFlowStore().removeSequences([id]);
        },
    },
};
