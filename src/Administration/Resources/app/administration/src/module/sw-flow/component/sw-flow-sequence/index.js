import template from './sw-flow-sequence.html.twig';
import './sw-flow-sequence.scss';

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

        isActionSequence() {
            return !this.isSelectorSequence && !this.isConditionSequence;
        },

        trueBlockClasses() {
            if (this.sequence.parentId || !this.isConditionSequence) {
                return null;
            }

            let falseBlock = this.sequence?.falseBlock;
            let trueBlock = this.sequence?.trueBlock;

            if (!falseBlock || !trueBlock) {
                return null;
            }

            falseBlock = Object.values(falseBlock);
            trueBlock = Object.values(trueBlock);

            // Check if both true block and false block are selector components
            return {
                'has--selector': falseBlock[0].actionName === null
                    && falseBlock[0].ruleId === null
                    && trueBlock[0].actionName === null
                    && trueBlock[0].ruleId === null,
            };
        },
    },
};
