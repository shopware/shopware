import { Component } from 'src/core/shopware';
import template from './sw-empty-state.html.twig';
import './sw-empty-state.less';

Component.register('sw-empty-state', {
    template,

    props: {
        title: {
            type: String,
            default: '',
            required: true
        }
    },

    computed: {
        moduleColor() {
            return this.$route.meta.$module.color;
        },

        moduleDescription() {
            return this.$route.meta.$module.description;
        },

        moduleIcon() {
            return this.$route.meta.$module.icon;
        },

        hasActionSlot() {
            return !!this.$slots.actions;
        }
    }
});
