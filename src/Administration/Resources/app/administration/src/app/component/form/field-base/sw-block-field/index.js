import template from './sw-block-field.html.twig';
import './sw-block-field.scss';

const { Component } = Shopware;

Component.register('sw-block-field', {
    template,
    inheritAttrs: false,

    props: {
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

    data() {
        return {
            hasFocus: false,
        };
    },

    computed: {
        swBlockSize() {
            return `sw-field--${this.size}`;
        },

        swBlockFieldClasses() {
            return [
                {
                    'has--focus': this.hasFocus,
                },
                this.swBlockSize,
            ];
        },
    },

    methods: {
        setFocusClass() {
            this.hasFocus = true;
        },

        removeFocusClass() {
            this.hasFocus = false;
        },
    },
});
