import template from './sw-compact-colorpicker.html.twig';
import './sw-compact-colorpicker.scss';

/**
 * @sw-package framework
 *
 * @private
 */
export default {
    template,

    inject: ['feature'],

    emits: ['update:value'],

    computed: {
        colorValue: {
            get() {
                return this.localValue;
            },
            set(newColor) {
                this.localValue = newColor;
            },
        },
    },

    methods: {
        emitColor() {
            this.$emit('update:value', this.localValue);
            this.visible = false;
        },
    },
};
