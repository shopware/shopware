import { Component } from 'src/core/shopware';
import template from './sw-media-grid.html.twig';
import './sw-media-grid.less';

/**
 * @private
 */
Component.register('sw-media-grid', {
    template,

    props: {
        presentation: {
            required: false,
            type: String,
            default: 'medium-preview',
            validator(value) {
                return ['small-preview', 'medium-preview', 'large-preview'].includes(value);
            }
        }
    },

    computed: {
        mediaColumnDefinitions() {
            return {
                'grid-template-columns': `repeat(auto-fit, ${this.gridColumnWidth}px)`
            };
        },
        presentationClass() {
            return `sw-media-grid__presentation-${this.presentation}`;
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

        isDragEvent(event) {
            return this.$parent.$parent.isDragEvent(event);
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
        }
    }
});
