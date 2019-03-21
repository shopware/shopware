import { Component } from 'src/core/shopware';
import './sw-multi-select-option.scss';
import template from './sw-multi-select-option.html.twig';

/**
 * @private
 */
Component.register('sw-multi-select-option', {
    template,

    props: {
        index: {
            type: Number,
            required: true
        },
        item: {
            type: Object,
            required: true
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false
        },
        selected: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            isActive: false
        };
    },

    computed: {
        componentClasses() {
            return [
                {
                    'is--active': this.isActive,
                    'is--disabled': this.disabled
                },
                `sw-multi-select-option--${this.index}`
            ];
        }
    },

    created() {
        this.createdComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            this.registerEvents();

            if (this.index === 0) {
                this.isActive = true;
            }
        },

        destroyedComponent() {
            this.removeEvents();
        },

        registerEvents() {
            this.$parent.$on('sw-multi-select-active-item-index', this.checkActiveState);
            this.$parent.$on('sw-multi-select-on-keyup-enter', this.selectOptionOnEnter);
        },

        removeEvents() {
            this.$parent.$off('sw-multi-select-active-item-index', this.checkActiveState);
            this.$parent.$off('sw-multi-select-on-keyup-enter', this.selectOptionOnEnter);
        },

        emitActiveResultPosition(originalDomEvent, index) {
            this.$emit({ originalDomEvent, index });
        },

        onClicked(originalDomEvent) {
            if (this.disabled) {
                return;
            }

            this.$parent.$emit('sw-multi-select-option-clicked', {
                originalDomEvent,
                item: this.item
            });
        },

        checkActiveState(index) {
            if (index === this.index) {
                this.isActive = true;
                return;
            }
            this.isActive = false;
        },

        selectOptionOnEnter(index) {
            if (index !== this.index) {
                return;
            }

            this.onClicked({});
        },

        isSelected(item) {
            return this.$parent.isSelected(item);
        },

        onMouseEnter(originalDomEvent) {
            this.$parent.$emit('sw-multi-select-option-mouse-over', { originalDomEvent, index: this.index });
            this.isActive = true;
        }
    }
});
