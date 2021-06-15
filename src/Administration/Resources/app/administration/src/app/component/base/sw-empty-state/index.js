import template from './sw-empty-state.html.twig';
import './sw-empty-state.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-empty-state', {
    template,

    props: {
        title: {
            type: String,
            default: '',
            required: true,
        },
        subline: {
            type: String,
            default: '',
            required: false,
        },
        showDescription: {
            type: Boolean,
            default: true,
            required: false,
        },
        color: {
            type: String,
            default: '',
            required: false,
        },
        icon: {
            type: String,
            default: '',
            required: false,
        },
        absolute: {
            type: Boolean,
            default: true,
            required: false,
        },
    },

    computed: {
        moduleColor() {
            return this.color || this.$route.meta.$module.color;
        },

        moduleDescription() {
            return this.subline || this.$tc(this.$route.meta.$module.description);
        },

        moduleIcon() {
            return this.icon || this.$route.meta.$module.icon;
        },

        hasActionSlot() {
            return !!this.$slots.actions;
        },

        classes() {
            return {
                'sw-empty-state--absolute': this.absolute,
            };
        },
    },
});
