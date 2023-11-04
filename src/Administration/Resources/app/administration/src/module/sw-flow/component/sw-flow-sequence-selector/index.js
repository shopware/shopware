import template from './sw-flow-sequence-selector.html.twig';
import './sw-flow-sequence-selector.scss';

const { State } = Shopware;

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
        title() {
            if (!this.sequence.parentId && this.sequence.position > 1) {
                return this.$tc('sw-flow.detail.sequence.selectorTitleAddSequence');
            }

            return this.$tc('sw-flow.detail.sequence.selectorTitle');
        },

        helpText() {
            const { parentId, position, trueCase } = this.sequence;

            if (!parentId && position === 1) {
                return this.$tc('sw-flow.detail.sequence.selectorHelpText');
            }

            if (!parentId && position > 1) {
                return this.$tc('sw-flow.detail.sequence.selectorHelpTextAddSequence');
            }

            if (trueCase) {
                return this.$tc('sw-flow.detail.sequence.selectorHelpTextTrueCondition');
            }

            return this.$tc('sw-flow.detail.sequence.selectorHelpTextFalseCondition');
        },
    },

    methods: {
        addIfCondition() {
            State.commit('swFlowState/updateSequence', {
                id: this.sequence.id,
                ruleId: '',
            });
        },

        addThenAction() {
            State.commit('swFlowState/updateSequence', {
                id: this.sequence.id,
                actionName: '',
            });
        },
    },
};
