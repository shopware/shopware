import { Component } from 'src/core/shopware';
import './sw-select-option.less';
import template from './sw-select-option.html.twig';

Component.register('sw-select-option', {
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
        }
    },

    data() {
        return {
            isActive: false
        };
    },

    computed: {
        componentClasses() {
            return {
                'is--active': this.isActive,
                'is--disabled': this.disabled
            };
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.registerEvents();
        },

        registerEvents() {
            this.$parent.$on('sw-select-active-item-index', this.checkActiveState);
            this.$parent.$on('sw-select-on-keyup-enter', this.checkIfOptionIsSelected);
        },

        emitActiveResultPosition(originalDomEvent, index) {
            this.$emit({ originalDomEvent, index });
        },

        onClicked(originalDomEvent) {
            if (!this.disabled) {
                this.$parent.$emit('sw-select-option-clicked', {
                    originalDomEvent,
                    item: this.item
                });
            }
        },

        checkActiveState(index) {
            if (index === this.index) {
                this.isActive = true;
                return;
            }
            this.isActive = false;
        },

        checkIfOptionIsSelected(index) {
            if (index === this.index) {
                this.onClicked({});
            }
        },

        isInSelections(item) {
            return this.$parent.isInSelections(item);
        },

        onMouseEnter(originalDomEvent) {
            this.$parent.$emit('sw-select-option-mouse-over', { originalDomEvent, index: this.index });
            this.isActive = true;
        }
    }
});
