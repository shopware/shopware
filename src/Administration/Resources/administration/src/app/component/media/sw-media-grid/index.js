import { Component } from 'src/core/shopware';
import template from './sw-media-grid.twig';
import gridStyles from '../gridStyles';
import './sw-media-grid.less';
import '../sw-media-grid-item';

Component.register('sw-media-grid', {
    template,

    props: {
        mediaGridStyle: {
            required: true,
            type: String,
            validator(value) {
                return [gridStyles.MEDIA_GRID_TYPE_LIST, gridStyles.MEDIA_GRID_TYPE_GRID].includes(value);
            }
        },
        gridColumnWidth: {
            required: false,
            default: 128,
            type: Number,
            validator(value) {
                return value > 0;
            }
        },
        mediaItems: {
            required: true,
            type: Array,
            validator() {
                /* TODO @SE: add validation for media-entity */
                return true;
            }
        }
    },

    data() {
        return {
            selection: []
        };
    },

    computed: {
        showItemsInline() {
            return this.mediaGridStyle === gridStyles.MEDIA_GRID_TYPE_LIST;
        },
        mediaColumnDefinitions() {
            let columnDefinition;

            if (this.showItemsInline) {
                columnDefinition = '100%';
            } else {
                columnDefinition = `repeat(auto-fit, ${this.gridColumnWidth}px)`;
            }

            return {
                'grid-template-columns': columnDefinition
            };
        },
        showCheckboxes() {
            return this.selection.length > 0;
        }
    },

    methods: {
        clearSelection() {
            this.selection = [];
        },
        isItemSelected(mediaItem) {
            if (this.selection.length === 0) {
                return false;
            }

            const index = this.selection.findIndex((element) => {
                return (element.id === mediaItem.id);
            });

            return index > -1;
        },
        addToSelection(mediaItem) {
            if (!this.isItemSelected(mediaItem)) {
                this.selection.push(mediaItem);
            }
        },
        removeFromSelection(mediaItem) {
            this.selection = this.selection.filter((element) => {
                return !(element.id === mediaItem.id);
            });
        }
    }
});
