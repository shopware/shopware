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
        hero: {
            type: Boolean,
            required: false,
            default: false
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
        },
        large: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        cardClasses() {
            return {
                'sw-card--tabs': !!this.$slots.tabs,
                'sw-card--grid': !!this.$slots.grid,
                'sw-card--hero': !!this.$props.hero,
                'sw-card--large': this.large
            };
        }
    }
});
