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
        this.$parent.$on('changed-active-item', this.checkIfActive);
    },

    destroyed() {
        this.$parent.$off('changed-active-item', this.checkIfActive);
    },

    methods: {
        checkIfActive(activeItemIndex) {
            this.active = this.index === activeItemIndex;
        },

        onClickResult(originalDomEvent) {
            if (this.disabled) {
                return;
            }

            this.$parent.$emit('item-select', this.item, originalDomEvent);
        },

        onMouseEnter() {
            this.setActiveItemIndex(this.index);
        }
    }
});
