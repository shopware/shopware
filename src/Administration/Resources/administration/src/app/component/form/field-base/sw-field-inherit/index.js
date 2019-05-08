import { Component } from 'src/core/shopware';
import template from './sw-field-inherit.html.twig';
import './sw-field-inherit.scss';

Component.register('sw-field-inherit', {
    template,

    props: {
        isInherited: {
            type: Boolean,
            required: true,
            default: false
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        unInheritClasses() {
            return { 'is--clickable': !this.disabled };
        }
    },

    methods: {
        onClickRestoreInheritance() {
            if (this.disabled) {
                return;
            }
            this.$emit('inheritance-restore');
        }
    }
});
