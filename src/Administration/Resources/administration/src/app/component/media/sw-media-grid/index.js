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
        thumbnailSize: {
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
                // add magical 24 = 2px border 10px padding for each left and right
                const columnWidth = 24 + this.thumbnailSize;
                columnDefinition = `repeat(auto-fit, ${columnWidth}px)`;
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

            return this.selection.find((element) => {
                return (element.id === mediaItem.id);
            }) !== undefined;
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
