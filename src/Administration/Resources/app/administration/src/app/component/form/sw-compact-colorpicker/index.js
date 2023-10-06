import template from './sw-compact-colorpicker.html.twig';
import './sw-compact-colorpicker.scss';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 */
Component.extend('sw-compact-colorpicker', 'sw-colorpicker', {
    template,

    inject: ['feature'],

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
            if (this.feature.isActive('VUE3')) {
                this.$emit('update:value', this.localValue);
                this.visible = false;

                return;
            }

            this.$emit('input', this.localValue);
            this.visible = false;
        },
    },
});
