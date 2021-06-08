import template from './sw-flow-detail-flow.html.twig';
import './sw-flow-detail-flow.scss';

const { Component, State } = Shopware;
const utils = Shopware.Utils;
const { cloneDeep } = Shopware.Utils.object;
const { mapGetters, mapState } = Component.getComponentHelper();

Component.register('sw-flow-detail-flow', {
    template,

    inject: ['repositoryFactory', 'acl'],

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

        ...mapState('swFlowState', ['flow']),
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
    },

    methods: {
        convertSequenceData() {
            if (!this.sequences) {
                return [];
            }

            const sequences = cloneDeep(this.sequences);

            const results = sequences.reduce((result, org) => {
                (result[org.displayGroup] ??= []).push(org);
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
                if (!node.parentId) {
                    sequence = node.actionName === null
                        ? node
                        : { ...sequence, [node.id]: node };
                    return;
                }

                const parentIndex = sequences.findIndex(el => el.id === node.parentId);

                if (!sequences[parentIndex]) {
                    return;
                }

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

            if (!this.rootSequences.length) {
                const sequence = this.createSequence();
                State.commit('swFlowState/addSequence', sequence);
            }
        },

        onAddRootSequence() {
            const newItem = this.createSequence();
            newItem.position = this.rootSequences.length + 1;
            newItem.displayGroup = this.rootSequences[this.rootSequences.length - 1].displayGroup + 1;

            State.commit('swFlowState/addSequence', newItem);
        },
    },
});
