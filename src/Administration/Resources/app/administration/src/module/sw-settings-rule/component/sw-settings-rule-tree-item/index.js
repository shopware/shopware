import template from './sw-settings-rule-tree-item.html.twig';

const { Component } = Shopware;

Component.extend('sw-settings-rule-tree-item', 'sw-tree-item', {
    template,

    props: {
        association: {
            type: String,
            required: true,
        },
        hideActions: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    methods: {
        hasItemAssociation(item) {
            return item.data[this.association]?.length > 0 || item.data.extensions[this.association]?.length > 0;
        },
    },
});
