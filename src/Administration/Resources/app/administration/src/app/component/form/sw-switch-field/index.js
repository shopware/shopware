import template from './sw-switch-field.html.twig';
import './sw-switch-field.scss';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 * @public
 * @description Boolean input field based on checkbox.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-switch-field v-model="aBooleanProperty" label="Name"></sw-switch-field>
 */
Component.extend('sw-switch-field', 'sw-checkbox-field', {
    template,

    inheritAttrs: false,

    props: {
        noMarginTop: {
            type: Boolean,
            required: false,
            default: false,
        },

        size: {
            type: String,
            required: false,
            default: 'default',
            validValues: ['small', 'medium', 'default'],
            validator(val) {
                return ['small', 'medium', 'default'].includes(val);
            },
        },
    },

    computed: {
        swSwitchFieldClasses() {
            return [
                {
                    'sw-field--switch-bordered': this.bordered,
                    'sw-field--switch-padded': this.padded,
                    'sw-field--switch-no-margin-top': this.noMarginTop,
                    ...this.swCheckboxFieldClasses,
                },
                `sw-field--${this.size}`,
            ];
        },
    },

    methods: {
        onInheritanceRestore(event) {
            this.$emit('inheritance-restore', event);
        },
    },
});
