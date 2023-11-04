import template from './sw-flow-sequence-condition.html.twig';
import './sw-flow-sequence-condition.scss';

const { Component, State } = Shopware;
const { Criteria } = Shopware.Data;
const utils = Shopware.Utils;
const { ShopwareError } = Shopware.Classes;
const { mapState, mapGetters } = Component.getComponentHelper();

/**
 * @private
 * @package business-ops
 */
export default {
    template,

    inject: [
        'repositoryFactory',
        'flowBuilderService',
        'feature',
    ],

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

    data() {
        return {
            showCreateRuleModal: false,
            showRuleSelection: false,
            fieldError: null,
            showAddButton: false,
            selectedRuleId: null,
        };
    },

    computed: {
        ...mapState('swFlowState', ['restrictedRules', 'flow']),

        sequenceRepository() {
            return this.repositoryFactory.create('flow_sequence');
        },

        ruleRepository() {
            return this.repositoryFactory.create('rule');
        },

        ruleCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },

        showHelpElement() {
            const { parentId, ruleId, trueBlock, falseBlock } = this.sequence;
            return !parentId && !ruleId && !(trueBlock || falseBlock);
        },

        modalName() {
            return this.flowBuilderService.getActionModalName(this.actionModal);
        },

        ruleDescription() {
            if (!this.sequence?.rule?.description) {
                return null;
            }

            return this.sequence.rule.description.replace(/\n/g, '<br>');
        },

        advanceSelectionParameters() {
            return {
                ruleAwareGroupKey: `flowTrigger.${this.flow.eventName}`,
            };
        },

        ...mapState('swFlowState', ['invalidSequences', 'flow']),
        ...mapGetters('swFlowState', ['sequences']),
    },

    watch: {
        sequence: {
            handler(value) {
                const { ruleId, parentId, trueBlock, falseBlock } = value;

                this.setFieldError();

                // Re-add selector
                if (parentId || !ruleId) {
                    return;
                }

                if (!trueBlock) {
                    this.createSequence({
                        parentId: this.sequence.id,
                        trueCase: true,
                    });
                }

                if (!falseBlock) {
                    this.createSequence({
                        parentId: this.sequence.id,
                        trueCase: false,
                    });
                }
            },
            immediate: true,
        },
    },

    methods: {
        onCreateNewRule() {
            this.showCreateRuleModal = true;
        },

        onCloseModal() {
            this.showCreateRuleModal = false;
            this.selectedRuleId = null;
        },

        onSaveRuleSuccess(rule) {
            this.onRuleChange(rule);
        },

        onRuleChange(rule) {
            if (!rule) {
                return;
            }

            State.commit('swFlowState/updateSequence', {
                id: this.sequence.id,
                rule,
                ruleId: rule.id,
            });

            if (this.selectedRuleId) {
                // Update other conditions which use the same rule
                this.sequences.forEach(sequence => {
                    if (sequence.ruleId !== this.selectedRuleId
                        || sequence.id === this.sequence.id) {
                        return;
                    }

                    State.commit('swFlowState/updateSequence', {
                        id: sequence.id,
                        rule,
                        ruleId: rule.id,
                    });
                });

                this.selectedRuleId = null;
            }

            this.removeFieldError();
            this.showRuleSelection = false;
        },

        deleteRule() {
            State.commit('swFlowState/updateSequence', {
                id: this.sequence.id,
                rule: null,
                ruleId: '',
            });
        },

        addIfCondition(trueCase) {
            this.createSequence({
                trueCase,
                ruleId: '',
            });
        },

        addThenAction(trueCase) {
            this.createSequence({
                trueCase,
                actionName: '',
            });
        },

        showArrowIcon(trueCase) {
            const { trueBlock, falseBlock } = this.sequence;

            if (trueCase) {
                if (!trueBlock) {
                    return false;
                }

                const sequence = Object.values(trueBlock)[0];
                return sequence.actionName !== null || sequence.ruleId !== null;
            }

            if (!falseBlock) {
                return false;
            }

            const sequence = Object.values(falseBlock)[0];
            return sequence.actionName !== null || sequence.ruleId !== null;
        },

        disabledAddSequence(trueCase) {
            const { trueBlock, falseBlock, parentId } = this.sequence;

            if (trueCase) {
                if (!trueBlock) {
                    return false;
                }

                return !parentId && !this.showArrowIcon(trueCase);
            }

            if (!falseBlock) {
                return false;
            }

            return !parentId && !this.showArrowIcon(trueCase);
        },

        arrowClasses(trueCase) {
            return {
                'is--disabled': this.disabledAddSequence(trueCase),
                'has--true-action': !this.sequence.trueBlock,
                'has--false-action': !this.sequence.falseBlock,
            };
        },

        removeCondition() {
            const actionIds = [this.sequence.id];

            const getRemoveIds = (sequence, sequenceIds = []) => {
                if (sequence.trueBlock) {
                    Object.values(sequence.trueBlock).forEach(trueSequence => {
                        if (trueSequence._isNew) {
                            sequenceIds.push(trueSequence.id);
                        }

                        getRemoveIds(trueSequence, sequenceIds);
                    });
                }

                if (sequence.falseBlock) {
                    Object.values(sequence.falseBlock).forEach(falseSequence => {
                        if (falseSequence._isNew) {
                            sequenceIds.push(falseSequence.id);
                        }

                        getRemoveIds(falseSequence, sequenceIds);
                    });
                }
            };

            getRemoveIds(this.sequence, actionIds);

            State.commit('swFlowState/removeSequences', actionIds);
        },

        createSequence(params) {
            let sequence = this.sequenceRepository.create();
            const newSequence = {
                ...sequence,
                parentId: this.sequence.id,
                displayGroup: this.sequence.displayGroup,
                actionName: params.actionName !== undefined ? params.actionName : null,
                ruleId: params.ruleId !== undefined ? params.ruleId : null,
                config: {},
                position: 1,
                trueCase: params.trueCase,
                id: utils.createId(),
            };

            sequence = Object.assign(sequence, newSequence);
            State.commit('swFlowState/addSequence', sequence);
        },

        setFieldError() {
            if (!this.invalidSequences?.includes(this.sequence.id)) {
                this.fieldError = null;
                return;
            }

            this.fieldError = new ShopwareError({
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            });
        },

        removeFieldError() {
            if (!this.fieldError) {
                return;
            }

            this.fieldError = null;
            const invalidSequences = this.invalidSequences?.filter(id => this.sequence.id !== id);
            State.commit('swFlowState/setInvalidSequences', invalidSequences);
        },

        toggleAddButton() {
            if (this.sequence.ruleId) {
                this.showRuleSelection = false;
                return;
            }

            this.showAddButton = !this.showAddButton;
        },

        onEditRule() {
            this.selectedRuleId = this.sequence?.rule?.id;
            this.showCreateRuleModal = true;
        },

        isRuleDisabled(rule) {
            return this.restrictedRules.includes(rule.id);
        },
    },
};
