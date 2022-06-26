import template from './sw-multi-snippet-drag-and-drop.html.twig';
import './sw-multi-snippet-drag-and-drop.scss';

const { Component } = Shopware;

Component.register('sw-multi-snippet-drag-and-drop', {
    template,

    model: {
        prop: 'value',
        event: 'change',
    },

    props: {
        value: {
            type: Array,
            required: true,
        },

        linePosition: {
            type: Number,
            required: true,
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        labelProperty: {
            type: String,
            required: false,
            default: 'label',
        },

        valueProperty: {
            type: String,
            required: false,
            default: 'value',
        },

        selectionDisablingMethod: {
            type: Function,
            required: false,
            default: () => false,
        },

        dragConfig: {
            type: Object,
            required: false,
            default() {
                return this.defaultConfig;
            },
        },

        dropConfig: {
            type: Object,
            required: false,
            default() {
                return this.defaultConfig;
            },
        },
    },

    data() {
        return {
            searchTerm: null,
            defaultConfig: {
                delay: 200,
                dragGroup: 'sw-multi-snippet',
                validDragCls: 'is--valid-drag',
                preventEvent: true,
                disabled: this.disabled,
            },
        };
    },

    computed: {
        errorObject() {
            return null;
        },

        mergedDragConfig() {
            return {
                ...this.defaultConfig,
                onDragStart: this.dragStart,
                onDragEnter: this.onDragEnter,
                onDrop: this.dragEnd,
                ...this.dragConfig,
            };
        },

        mergedDropConfig() {
            return {
                ...this.defaultConfig,
                ...this.dropConfig,
            };
        },
    },

    methods: {
        dragStart(config, element, dragElement) {
            this.$emit('drag-start', { config, element, dragElement });
        },

        onDragEnter(dragData, dropData) {
            if (!dragData || !dropData) {
                return;
            }

            this.$emit('drag-enter', { dragData, dropData });
        },

        dragEnd(dragData, dropData) {
            if (!dragData || !dropData) {
                return;
            }

            this.$emit('drag-end', this.linePosition, { dragData, dropData });
        },

        isSelectionDisabled(selection) {
            if (this.disabled) {
                return true;
            }

            return this.selectionDisablingMethod(selection);
        },

        onClickDismiss(index) {
            this.$emit(
                'change',
                this.linePosition,
                this.value.filter((_, key) => key !== index),
            );
        },

        addNewLineAt(position) {
            this.$emit('add-new-line', this.linePosition, position);
        },

        moveToLocation(position = null) {
            this.$emit('location-move', this.linePosition, position);
        },

        onDelete() {
            this.$emit('change', this.linePosition, [], true);
        },

        openModal() {
            this.$emit('open-snippet-modal', this.linePosition);
        },
    },
});
