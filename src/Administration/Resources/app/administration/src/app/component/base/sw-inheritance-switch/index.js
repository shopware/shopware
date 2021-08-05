import template from './sw-inheritance-switch.html.twig';
import './sw-inheritance-switch.scss';

const { Component } = Shopware;

Component.register('sw-inheritance-switch', {
    template,

    props: {
        isInherited: {
            type: Boolean,
            required: true,
            default: false,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        unInheritClasses() {
            return { 'is--clickable': !this.disabled };
        },
    },

    methods: {
        onClickRestoreInheritance() {
            if (this.disabled) {
                return;
            }
            this.$emit('inheritance-restore');
        },

        onClickRemoveInheritance() {
            if (this.disabled) {
                return;
            }
            this.$emit('inheritance-remove');
        },
    },
});
