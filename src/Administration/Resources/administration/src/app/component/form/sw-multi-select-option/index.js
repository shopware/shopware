import './sw-multi-select-option.scss';
import template from './sw-multi-select-option.html.twig';

const { Component } = Shopware;

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
            this.$parent.$on('active-item-index-select', this.checkActiveState);
            this.$parent.$on('keyup-enter', this.selectOptionOnEnter);
        },

        removeEvents() {
            this.$parent.$off('active-item-index-select', this.checkActiveState);
            this.$parent.$off('keyup-enter', this.selectOptionOnEnter);
        },

        emitActiveResultPosition(originalDomEvent, index) {
            this.$emit({ originalDomEvent, index });
        },

        onClicked(originalDomEvent) {
            if (this.disabled) {
                return;
            }

            this.$parent.$emit('option-click', {
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
            this.$parent.$emit('option-mouse-over', { originalDomEvent, index: this.index });
            this.isActive = true;
        }
    }
});
