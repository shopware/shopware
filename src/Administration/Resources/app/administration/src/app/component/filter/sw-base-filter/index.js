import template from './sw-base-filter.html.twig';
import './sw-base-filter.scss';

const { Component } = Shopware;

Component.register('sw-base-filter', {
    template,

    props: {
        title: {
            type: String,
            required: true
        }
    },

    methods: {
        resetFilter() {
            this.$emit('resetFilter');
        }
    }
});
