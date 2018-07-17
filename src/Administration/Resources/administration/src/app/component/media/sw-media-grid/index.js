import { Component } from 'src/core/shopware';
import template from './sw-media-grid.html.twig';
import './sw-media-grid.less';

Component.register('sw-media-grid', {
    template,

    props: {
        previewType: {
            required: true,
            type: String,
            validator(value) {
                return [
                    'media-grid-preview-as-grid',
                    'media-grid-preview-as-list'
                ].includes(value);
            }
        },
        previewComponent: {
            require: true,
            type: String
        },
        items: {
            required: true,
            type: Array
        },
        idField: {
            required: false,
            default: 'id',
            type: String
        },
        editable: {
            required: false,
            type: Boolean,
            default: false
        },
        selectable: {
            required: false,
            type: Boolean,
            default: true
        },
        gridColumnWidth: {
            required: false,
            type: Number,
            default: 200,
            validator(value) {
                return value > 0;
            }
        }
    },

    data() {
        return {
            selection: []
        };
    },

    computed: {
        mediaColumnDefinitions() {
            let columnDefinition;

            switch (this.previewType) {
            case 'media-grid-preview-as-list':
                columnDefinition = '100%';
                break;

            case 'media-grid-preview-as-grid':
            default:
                columnDefinition = `repeat(auto-fit, ${this.gridColumnWidth}px)`;
            }

            return {
                'grid-template-columns': columnDefinition
            };
        },
        showSelectedIndicator() {
            return this.selectable && this.selection.length > 0;
        },
        containerOptions() {
            return {
                previewType: this.previewType,
                selectionInProgress: this.showSelectedIndicator,
                previewSize: this.gridColumnWidth,
                selectable: this.selectable,
                editable: this.editable
            };
        }
    },

    methods: {
        clearSelection() {
            this.selection = [];
        },
        isItemSelected(item) {
            if (this.selection.length === 0) {
                return false;
            }

            const index = this.selection.findIndex((element) => {
                return (element[this.idField] === item[this.idField]);
            });

            return index > -1;
        },
        addToSelection(item) {
            if (!this.selectable) {
                return;
            }

            if (!this.isItemSelected(item)) {
                this.selection.push(item);
            }
        },
        removeFromSelection(item) {
            this.selection = this.selection.filter((element) => {
                return !(element[this.idField] === item[this.idField]);
            });
        },
        forwardItemEvent(mediaItemEvent) {
            this.$emit(mediaItemEvent);
        },
        forwardBatchEvent(mediaItemEvent) {
            mediaItemEvent.selection = this.selection;
            this.$emit(mediaItemEvent);
        }
    }
});
