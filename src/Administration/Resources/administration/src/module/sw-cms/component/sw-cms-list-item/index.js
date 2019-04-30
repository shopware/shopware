import { Component } from 'src/core/shopware';
import template from './sw-cms-list-item.html.twig';
import './sw-cms-list-item.scss';

Component.register('sw-cms-list-item', {
    template,

    props: {
        page: {
            type: Object,
            required: false,
            default: null
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {}
    }
});
