import template from './sw-cms-sidebar-nav-element.html.twig';
import './sw-cms-sidebar-nav-element.scss';

const { Component } = Shopware;


Component.register('sw-cms-sidebar-nav-element', {
    template,

    props: {
        block: {
            type: Object,
            required: true,
        },

        removable: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    methods: {
        onBlockDuplicate() {
            this.$emit('block-duplicate', this.block);
        },

        onBlockDelete() {
            this.$emit('block-delete', this.block);
        },
    },
});
