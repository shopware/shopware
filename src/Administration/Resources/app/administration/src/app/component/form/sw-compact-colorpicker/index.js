import template from './sw-compact-colorpicker.html.twig';
import './sw-compact-colorpicker.scss';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 */
Component.extend('sw-compact-colorpicker', 'sw-colorpicker-deprecated', {
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
});
