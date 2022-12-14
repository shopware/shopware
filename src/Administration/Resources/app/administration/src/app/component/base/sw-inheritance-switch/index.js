/**
 * @package admin
 */

import template from './sw-inheritance-switch.html.twig';
import './sw-inheritance-switch.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
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
