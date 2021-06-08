import template from './sw-flow-sequence.html.twig';
import './sw-flow-sequence.scss';

const { Component } = Shopware;

Component.register('sw-flow-sequence', {
    template,

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

    computed: {
        sequenceData() {
            const values = Object.values(this.sequence);

            // Check if the current sequence is a root sequence or an action list sequence
            if (this.sequence.id || values.length > 1) {
                return this.sequence;
            }

            return values[0];
        },

        isSelectorSequence() {
            return this.sequenceData.actionName === null && this.sequenceData.ruleId === null;
        },

        isConditionSequence() {
            return this.sequenceData.ruleId || this.sequenceData.ruleId === '';
        },
    },
});
