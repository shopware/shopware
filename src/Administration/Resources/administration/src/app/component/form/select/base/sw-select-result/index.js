import './sw-select-result.scss';
import template from './sw-select-result.html.twig';

const { Component } = Shopware;

/**
 * @public
 * @status ready
 * @description Base component for select results.
 * @example-type code-only
 */
Component.register('sw-select-result', {
    template,

    inject: ['setActiveItemIndex'],

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
            active: false
        };
    },

    computed: {
        resultClasses() {
            return [
                {
                    'is--active': this.active,
                    'is--disabled': this.disabled
                },
                `sw-select-option--${this.index}`
            ];
        }
    },

    created() {
        this.$parent.$on('active-item-change', this.checkIfActive);
        this.$parent.$on('item-select-by-keyboard', this.checkIfSelected);
    },

    destroyed() {
        this.$parent.$off('active-item-change', this.checkIfActive);
        this.$parent.$off('item-select-by-keyboard', this.checkIfSelected);
    },

    methods: {
        checkIfSelected(selectedItemIndex) {
            if (selectedItemIndex === this.index) this.onClickResult({});
        },

        checkIfActive(activeItemIndex) {
            this.active = this.index === activeItemIndex;
        },

        onClickResult() {
            if (this.disabled) {
                return;
            }

            this.$parent.$emit('item-select', this.item);
        },

        onMouseEnter() {
            this.setActiveItemIndex(this.index);
        }
    }
});
