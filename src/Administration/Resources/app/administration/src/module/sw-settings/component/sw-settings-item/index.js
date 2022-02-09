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
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
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
