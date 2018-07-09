import { Component } from 'src/core/shopware';
import template from './sw-card.html.twig';
import './sw-card.less';

Component.register('sw-card', {
    template,

    props: {
        title: {
            type: String,
            required: false
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false
        },
        grid: {
            type: String,
            required: false,
            default: ''
        }
    },

    computed: {
        cardClasses() {
            return {
                'sw-card--tabs': !!this.$slots.tabs,
                'sw-card--grid': !!this.$slots.grid
            };
        }
    }
});
