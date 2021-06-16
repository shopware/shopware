import template from './sw-settings-item.html.twig';
import './sw-settings-item.scss';

const { Component } = Shopware;

Component.register('sw-settings-item', {
    template,

    props: {
        label: {
            required: true,
            type: String,
        },
        to: {
            required: true,
            type: Object,
            default() {
                return {};
            },
        },
        disabled: {
            required: false,
            type: Boolean,
            default: false,
        },
        backgroundEnabled: {
            required: false,
            type: Boolean,
            default: true,
        },
    },

    computed: {
        classes() {
            return {
                'is--disabled': this.disabled,
            };
        },
    },
});
