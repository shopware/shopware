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
            required: true,
        },
        item: {
            type: Object,
            required: true,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
        selected: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            active: false,
        };
    },

    computed: {
        resultClasses() {
            return [
                {
                    'is--active': this.active,
                    'is--disabled': this.disabled,
                },
                `sw-select-option--${this.index}`,
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            this.$parent.$parent.$on('active-item-change', this.checkIfActive);
            this.$parent.$parent.$on('item-select-by-keyboard', this.checkIfSelected);
        },

        destroyedComponent() {
            this.$parent.$parent.$off('active-item-change', this.checkIfActive);
            this.$parent.$parent.$off('item-select-by-keyboard', this.checkIfSelected);
        },

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

            this.$parent.$parent.$emit('item-select', this.item);
        },

        onMouseEnter() {
            this.setActiveItemIndex(this.index);
        },
    },
});
