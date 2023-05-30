import template from './sw-flow-detail-flow.html.twig';
import './sw-flow-detail-flow.scss';

const { Component, State } = Shopware;
const utils = Shopware.Utils;
const { cloneDeep } = Shopware.Utils.object;
const { mapGetters, mapState } = Component.getComponentHelper();

/**
 * @private
 * @package business-ops
 */
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'flowActionService',
        'ruleConditionDataProviderService',
    ],

    props: {
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
        isNewFlow: {
            type: Boolean,
            required: false,
            default: false,
        },
        isTemplate: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            flowContainerStyle: null,
        };
    },

    computed: {
        sequenceRepository() {
            return this.repositoryFactory.create('flow_sequence');
        },

        formatSequences() {
            return this.convertSequenceData();
        },

        rootSequences() {
            return this.sequences.filter(sequence => !sequence.parentId);
        },

        showActionWarning() {
            if (!this.triggerActions.length || !this.sequences.length) {
                return false;
            }

            let showWarning = false;
            this.sequences.filter(action => action.actionName).forEach(sequence => {
                const actionInvalid = this.triggerActions.find(item => item.name === sequence.actionName);
                if (!actionInvalid) {
                    showWarning = true;
                }
            });

            return showWarning;
        },

        ...mapState('swFlowState', ['flow', 'triggerActions']),
        ...mapGetters('swFlowState', ['sequences']),
    },

    watch: {
        rootSequences: {
            handler(value) {
                if (!this.flow.eventName) {
                    return;
                }

                if (!value.length) {
                    const sequence = this.createSequence();
                    State.commit('swFlowState/addSequence', sequence);
                }
            },
            immediate: true,
        },

        sequences: {
            handler() {
                const sequenceContainers = document.getElementsByName('root-sequence');
                let maxWidth = 0;

                this.$nextTick(() => {
                    Array.from(sequenceContainers).forEach((item) => {
                        maxWidth = item.offsetWidth > maxWidth ? item.offsetWidth : maxWidth;
                    });

                    if (maxWidth <= 870) {
                        this.flowContainerStyle = null;
                        return;
                    }

                    if (maxWidth > 870 && maxWidth <= 1300) {
                        this.flowContainerStyle = { 'max-width': '1300px' };
                        return;
                    }

                    this.flowContainerStyle = { 'max-width': '100%' };
                });
            },
            immediate: true,
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.triggerActions?.length) {
                this.getTriggerActions();
            }
        },

        getTriggerActions() {
            return this.flowActionService.getActions().then((actions) => {
                State.commit('swFlowState/setTriggerActions', actions);
            });
        },

        convertSequenceData() {
            if (!this.sequences) {
                return [];
            }

            const sequences = cloneDeep(this.sequences);

            // Group sequences by its displayGroup, then those groups are arranged in ascending order.
            const results = sequences.reduce((result, sequence) => {
                if (!Array.isArray(result[sequence.displayGroup])) {
                    result[sequence.displayGroup] = [];
                }

                result[sequence.displayGroup].push(sequence);
                return result;
            }, {});

            return Object.values(results).reduce((result, item) => {
                const rootSequence = this.convertToTreeData(item);

                if (rootSequence) {
                    result.push(rootSequence);
                }

                return result;
            }, []);
        },

        convertToTreeData(sequences) {
            let sequence = null;

            sequences.forEach(node => {
                // Check if node is a root sequence
                if (!node.parentId) {
                    sequence = node.actionName === null
                        ? node
                        : { ...sequence, [node.id]: node }; // Generate action groups
                    return;
                }

                const parentIndex = sequences.findIndex(el => el.id === node.parentId);

                // Skip node parent does not existed
                if (!sequences[parentIndex]) {
                    return;
                }

                // Child node is assigned to parent's true block or false block based on their trueCase
                if (node.trueCase) {
                    sequences[parentIndex].trueBlock = {
                        ...sequences[parentIndex].trueBlock,
                        [node.id]: node,
                    };
                } else {
                    sequences[parentIndex].falseBlock = {
                        ...sequences[parentIndex].falseBlock,
                        [node.id]: node,
                    };
                }
            });

            return sequence;
        },

        createSequence() {
            let sequence = this.sequenceRepository.create();
            const newSequence = {
                ...sequence,
                parentId: null,
                ruleId: null,
                actionName: null,
                config: {},
                position: 1,
                displayGroup: 1,
                id: utils.createId(),
            };

            sequence = Object.assign(sequence, newSequence);
            return sequence;
        },

        onEventChange(eventName) {
            State.commit('swFlowState/setEventName', eventName);
            State.commit('error/removeApiError', {
                expression: `flow.${this.flow.id}.eventName`,
            });

            if (!this.rootSequences.length) {
                const sequence = this.createSequence();
                State.commit('swFlowState/addSequence', sequence);
            }
        },

        onAddRootSequence() {
            if (!this.acl.can('flow.editor')) {
                return;
            }

            const newItem = this.createSequence();
            newItem.position = 1;
            newItem.displayGroup = this.rootSequences[this.rootSequences.length - 1].displayGroup + 1;

            State.commit('swFlowState/addSequence', newItem);
        },

        getSequenceId(sequence) {
            if (sequence.id) {
                return sequence.displayGroup;
            }

            // In case of action sequence list, return displayGroup of first item
            return Object.values(sequence)[0].displayGroup;
        },
    },
};
