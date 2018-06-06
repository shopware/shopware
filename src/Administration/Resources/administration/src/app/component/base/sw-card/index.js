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
        hasTabsSlot() {
            return !!this.$slots.tabs;
        },

        hasGridSlot() {
            return !!this.$slots.grid;
        }
    }
});
