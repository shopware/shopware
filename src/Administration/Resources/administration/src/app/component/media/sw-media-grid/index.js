import { Component, Mixin } from 'src/core/shopware';
import template from './sw-media-grid.html.twig';
import './sw-media-grid.less';

/**
 * @private
 */
Component.register('sw-media-grid', {
    template,

    mixins: [
        Mixin.getByName('drag-selector')
    ],

    props: {
        gridColumnWidth: {
            required: false,
            type: Number,
            default: 200,
            validator(value) {
                return value > 0;
            }
        }
    },

    computed: {
        mediaColumnDefinitions() {
            return {
                'grid-template-columns': `repeat(auto-fit, ${this.gridColumnWidth}px)`
            };
        }
    },

    created() {
        this.componentCreated();
    },

    beforeDestroy() {
        this.beforeComponentDestroyed();
    },

    methods: {
        componentCreated() {
            window.addEventListener('click', this.clearSelectionOnClickOutside, false);
        },

        beforeComponentDestroyed() {
            window.removeEventListener('click', this.clearSelectionOnClickOutside);
        },

        clearSelectionOnClickOutside(event) {
            if (!this.isDragEvent(event) &&
              !this.isEmittedFromChildren(event.target) &&
              !this.isEmittedFromContextMenu(event.composedPath()) &&
              !this.isEmittedFromSidebar(event.composedPath())) {
                this.emitSelectionCleared(event);
            }
        },

        isEmittedFromSidebar(path) {
            return path.some((parent) => {
                return parent.classList && parent.classList.contains('sw-media-sidebar');
            });
        },

        isEmittedFromChildren(target) {
            return this.$children.some((child) => {
                return child.$el === target || child.$el.contains(target);
            });
        },

        isEmittedFromContextMenu(path) {
            return path.some((parent) => {
                return parent.classList && parent.classList.contains('sw-context-menu');
            });
        },

        emitSelectionCleared(originalDomEvent) {
            this.$emit('sw-media-grid-selection-clear', {
                originalDomEvent
            });
        },

        onDragSelection({ originalDomEvent, item }) {
            item.selectItem(originalDomEvent);
        },

        onDragDeselection({ originalDomEvent, item }) {
            item.removeFromSelection(originalDomEvent);
        }
    }
});
