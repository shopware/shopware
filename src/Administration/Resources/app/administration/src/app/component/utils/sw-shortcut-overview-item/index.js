import template from './sw-shortcut-overview-item.html.twig';
import './sw-shortcut-overview-item.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-shortcut-overview-item', {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['acl'],

    props: {
        title: {
            type: String,
            required: true,
        },
        content: {
            type: String,
            required: true,
        },
        privilege: {
            type: String,
            required: false,
            default: null,
        },
    },

    computed: {
        showItem() {
            return this.acl.can(this.privilege);
        },

        keys() {
            return this.content.split(' ') || [];
        },
    },
});
