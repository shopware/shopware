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
        loading: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        hasTabsSlot() {
            return !!this.$slots.tabs;
        }
    }
});
