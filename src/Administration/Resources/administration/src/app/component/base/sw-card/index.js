import { Component } from 'src/core/shopware';
import './sw-card.less';
import template from './sw-card.html.twig';

Component.register('sw-card', {
    template,

    props: {
        title: {
            type: String,
            required: true
        }
    },

    computed: {
        hasTabsSlot() {
            return !!this.$slots.tabs;
        }
    }
});
