import { Component } from 'src/core/shopware';
import template from './sw-collapse.html.twig';

Component.register('sw-collapse', {
    template,

    props: {
        expandOnLoading: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            expanded: this.expandOnLoading
        };
    },

    methods: {
        collapseItem() {
            this.expanded = !this.expanded;
        }
    }
});
