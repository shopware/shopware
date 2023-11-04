import template from './sw-media-grid.html.twig';
import './sw-media-grid.scss';

/**
 * @private
 * @package content
 */
export default {
    template,

    props: {
        presentation: {
            required: false,
            type: String,
            default: 'medium-preview',
            validator(value) {
                return ['small-preview', 'medium-preview', 'large-preview', 'list-preview'].includes(value);
            },
        },
    },

    computed: {
        mediaColumnDefinitions() {
            return {
                'grid-template-columns': `repeat(auto-fit, ${this.gridColumnWidth}px)`,
            };
        },

        presentationClass() {
            return `sw-media-grid__presentation--${this.presentation}`;
        },

        nonDeselectingComponents() {
            return [
                'sw-media-sidebar',
                'sw-context-menu',
                'sw-media-index__load-more',
                'sw-media-index__options-container',
                'sw-modal',
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        this.beforeDestroyComponent();
    },

    methods: {
        createdComponent() {
            window.addEventListener('click', this.clearSelectionOnClickOutside, false);
        },

        beforeDestroyComponent() {
            window.removeEventListener('click', this.clearSelectionOnClickOutside);
        },

        clearSelectionOnClickOutside(event) {
            if (!this.isEmittedFromChildren(event.target) &&
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
                [],
            );

            return this.nonDeselectingComponents.some((cssClass) => { return eventPathClasses.includes(cssClass); });
        },

        isEmittedFromChildren(target) {
            return this.$children.some((child) => {
                return child.$el === target || child.$el.contains(target);
            });
        },

        emitSelectionCleared(originalDomEvent) {
            this.$emit('media-grid-selection-clear', {
                originalDomEvent,
            });
        },
    },
};
