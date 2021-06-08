import template from './sw-flow-sequence-action.html.twig';
import './sw-flow-sequence-action.scss';
import { ACTION } from '../../constant/flow.constant';

const { Component, State } = Shopware;
const utils = Shopware.Utils;
const { ShopwareError } = Shopware.Classes;
const { mapState } = Component.getComponentHelper();

Component.register('sw-flow-sequence-action', {
    template,

    inject: ['repositoryFactory'],

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
            showModal: false,
            showAddButton: true,
            fieldError: null,
        };
    },

    computed: {
        sequenceRepository() {
            return this.repositoryFactory.create('flow_sequence');
        },

        actionOptions() {
            return [{
                value: ACTION.ADD_TAG,
                icon: 'default-action-tags',
                label: this.$tc('sw-flow.actions.addTag'),
            }, {
                value: ACTION.CALL_URL,
                icon: 'default-web-link',
                label: this.$tc('sw-flow.actions.callURL'),
            }, {
                value: ACTION.GENERATE_DOCUMENT,
                icon: 'default-documentation-file',
                label: this.$tc('sw-flow.actions.generateDocument'),
            }, {
                value: ACTION.REMOVE_TAG,
                icon: 'default-action-tags',
                label: this.$tc('sw-flow.actions.removeTag'),
            }, {
                value: ACTION.SEND_MAIL,
                icon: 'default-communication-envelope',
                label: this.$tc('sw-flow.actions.sendEmail'),
            }, {
                value: ACTION.SET_STATUS,
                icon: 'default-shopping-plastic-bag',
                label: this.$tc('sw-flow.actions.setStatus'),
            }, {
                value: ACTION.STOP_FLOW,
                icon: 'default-basic-x-circle',
                label: this.$tc('sw-flow.actions.stopFlow'),
            }];
        },

        sequenceData() {
            if (this.sequence.id) {
                return [
                    {
                        ...this.sequence,
                        ...this.getActionInfo(this.sequence.actionName),
                    },
                ];
            }

            return this.sortByPosition(Object.values(this.sequence).map(item => {
                return {
                    ...item,
                    ...this.getActionInfo(item.actionName),
                };
            }));
        },

        showAddAction() {
            return !(
                this.sequence.actionName === ACTION.STOP_FLOW ||
                this.sequenceData.some(sequence => sequence.actionName === ACTION.STOP_FLOW)
            );
        },

        actionClasses() {
            return {
                'is--stop-flow': !this.showAddAction,
            };
        },

        ...mapState('swFlowState', ['invalidSequences']),
    },

    watch: {
        sequence: {
            handler() {
                this.setFieldError();
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.showAddButton = this.sequenceData.length > 1 || !!this.sequence?.actionName;
        },

        openModal(value) {
            this.showModal = true;

            this.addAction(value);
        },

        addAction(value) {
            if (!value) {
                return;
            }

            if (!this.sequence.actionName && this.sequence.id) {
                State.commit('swFlowState/updateSequence', {
                    id: this.sequence.id,
                    actionName: value,
                });
            } else {
                const lastSequence = this.sequenceData[this.sequenceData.length - 1];

                let sequence = this.sequenceRepository.create();
                const newSequence = {
                    ...sequence,
                    parentId: lastSequence.parentId,
                    trueCase: lastSequence.trueCase,
                    displayGroup: lastSequence.displayGroup,
                    ruleId: null,
                    actionName: value,
                    position: lastSequence.position + 1,
                    config: {},
                    id: utils.createId(),
                };

                sequence = Object.assign(sequence, newSequence);
                State.commit('swFlowState/addSequence', sequence);
            }

            this.removeFieldError();
            this.toggleAddButton();
        },

        removeAction(id) {
            State.commit('swFlowState/removeSequences', [id]);
        },

        removeActionContainer() {
            const removeSequences = this.sequence.id ? [this.sequence.id] : Object.keys(this.sequence);

            State.commit('swFlowState/removeSequences', removeSequences);
        },

        getActionInfo(actionName) {
            return this.actionOptions.find(item => item.value === actionName);
        },

        toggleAddButton() {
            this.showAddButton = !this.showAddButton;
        },

        sortByPosition(sequences) {
            return sequences.sort((prev, current) => {
                return prev.position - current.position;
            });
        },

        stopFlowStyle(value) {
            return {
                'is--stop-flow': value === ACTION.STOP_FLOW,
            };
        },

        getActionDescription(sequence) {
            if (sequence.actionName === ACTION.STOP_FLOW) {
                return this.$tc('sw-flow.actions.textStopFlowDescription');
            }

            // TODO: NEXT-15781 - Generate action description
            return 'Description';
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

        isNotStopFlow(item) {
            return item.actionName !== ACTION.STOP_FLOW;
        },
    },
});
