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
        },
        nonDeselectingComponents() {
            return [
                'sw-media-sidebar',
                'sw-context-menu',
                'sw-media-index__load-more',
                'sw-media-index__options-container',
                'sw-modal'
            ];
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
                !this.originatesFromExcludedComponent(event)
            ) {
                this.emitSelectionCleared(event);
            }
        },

        originatesFromExcludedComponent(event) {
            const eventPathClasses = event.composedPath().reduce(
                (classes, eventParent) => {
                    return eventParent.classList ? classes.concat(Array.from(eventParent.classList)) : classes;
                },
                []
            );

            return this.nonDeselectingComponents.some((cssClass) => { return eventPathClasses.includes(cssClass); });
        },

        isDragEvent(event) {
            return this.$parent.$parent.isDragEvent(event);
        },

        isEmittedFromChildren(target) {
            return this.$children.some((child) => {
                return child.$el === target || child.$el.contains(target);
            });
        },

        emitSelectionCleared(originalDomEvent) {
            this.$emit('sw-media-grid-selection-clear', {
                originalDomEvent
            });
        }
    }
});
