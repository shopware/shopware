import template from './sw-media-grid.html.twig';
import './sw-media-grid.scss';

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement, there is now explicit unselect
     */
    emits: ['media-grid-selection-clear'],

    props: {
        presentation: {
            required: false,
            type: String,
            default: 'medium-preview',
            validator(value) {
                return [
                    'small-preview',
                    'medium-preview',
                    'large-preview',
                    'list-preview',
                ].includes(value);
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

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement, there is now explicit unselect
         */
        nonDeselectingComponents() {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-media-grid.nonDeselectingComponents is deprecated and will be removed without replacement, there is now explicit unselect.',
            );

            return [
                'sw-media-sidebar',
                'sw-context-menu',
                'sw-media-index__load-more',
                'sw-media-index__options-container',
                'sw-modal',
                'mt-modal',
                'mt-modal-root__backdrop',
            ];
        },
    },

    methods: {
        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement, there is now explicit unselect
         */
        createdComponent() {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-media-grid.createdComponent() is deprecated and will be removed without replacement, there is now explicit unselect.',
            );

            window.addEventListener('click', this.clearSelectionOnClickOutside, false);
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement, there is now explicit unselect
         */
        beforeDestroyComponent() {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-media-grid.beforeDestroyComponent() is deprecated and will be removed without replacement, there is now explicit unselect.',
            );

            window.removeEventListener('click', this.clearSelectionOnClickOutside, false);
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement, there is now explicit unselect
         */
        clearSelectionOnClickOutside(event) {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-media-grid.clearSelectionOnClickOutside() is deprecated and will be removed without replacement, there is now explicit unselect.',
            );

            if (!this.isEmittedFromChildren(event.target) && !this.originatesFromExcludedComponent(event)) {
                this.emitSelectionCleared(event);
            }
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement, there is now explicit unselect
         */
        originatesFromExcludedComponent(event) {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-media-grid.originatesFromExcludedComponent() is deprecated and will be removed without replacement, there is now explicit unselect.',
            );

            const eventPathClasses = event.composedPath().reduce((classes, eventParent) => {
                return eventParent.classList ? classes.concat(Array.from(eventParent.classList)) : classes;
            }, []);

            return this.nonDeselectingComponents.some((cssClass) => {
                return eventPathClasses.includes(cssClass);
            });
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement, there is now explicit unselect
         */
        isEmittedFromChildren(target) {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-media-grid.isEmittedFromChildren() is deprecated and will be removed without replacement, there is now explicit unselect.',
            );

            return this.$refs.componentRef?.contains(target) ?? false;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement, there is now explicit unselect
         */
        emitSelectionCleared(originalDomEvent) {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-media-grid.emitSelectionCleared() is deprecated and will be removed without replacement, there is now explicit unselect.',
            );

            this.$emit('media-grid-selection-clear', {
                originalDomEvent,
            });
        },
    },
};
