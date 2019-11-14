import template from './sw-shortcut-overview-item.html.twig';
import './sw-shortcut-overview-item.scss';

const { Component } = Shopware;

Component.register('sw-shortcut-overview-item', {
    template,

    props: {
        title: {
            type: String,
            required: true
        },
        content: {
            type: String,
            required: true
        }
    },

    computed: {
        keys() {
            return this.content.split(' ') || [];
        }
    }
});
