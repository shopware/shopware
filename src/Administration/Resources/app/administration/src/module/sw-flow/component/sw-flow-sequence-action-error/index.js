import useSwFlowStore from 'shopware:stores/swFlow';
import template from './sw-flow-sequence-action-error.html.twig';
import './sw-flow-sequence-action-error.scss';

const { Component } = Shopware;
const { mapState } = Component.getComponentHelper();

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
        ...mapState(() => useSwFlowStore(), ['sequences']),
    },

    methods: {
        removeWarning(id) {
            const action = this.sequences.find((sequence) => sequence.id === id);
            if (action?.id) {
                const sequencesInGroup = this.sequences.filter(
                    (item) => item.parentId === action.parentId && item.trueCase === action.trueCase && item.id !== id,
                );

                sequencesInGroup.forEach((item, index) => {
                    useSwFlowStore().updateSequence({
                        id: item.id,
                        position: index + 1,
                    });
                });
            }

            useSwFlowStore().removeSequences([id]);
        },
    },
};
