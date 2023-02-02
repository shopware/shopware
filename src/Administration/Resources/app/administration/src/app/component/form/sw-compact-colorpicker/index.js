import template from './sw-compact-colorpicker.html.twig';
import './sw-compact-colorpicker.scss';

const { Component } = Shopware;

/**
* @private
*/
Component.extend('sw-compact-colorpicker', 'sw-colorpicker', {
    template,

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
            this.$emit('input', this.localValue);
            this.visible = false;
        },
    },
});
