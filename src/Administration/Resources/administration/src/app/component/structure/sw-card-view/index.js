import { Component } from 'src/core/shopware';
import dom from 'src/core/service/utils/dom.utils';
import template from './sw-card-view.html.twig';
import './sw-card-view.less';

Component.register('sw-card-view', {
    template,

    props: {
        sidebar: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            scrollbarWidth: '0px'
        };
    },

    computed: {
        adjustScrollbar() {
            return {
                right: this.scrollbarWidth
            };
        }
    },

    updated() {
        this.componentUpdated();
    },

    methods: {
        componentUpdated() {
            const scrollbarWidth = dom.getScrollbarWidth(this.$refs.cardContainer);
            this.scrollbarWidth = `${scrollbarWidth}px`;
        }
    }
});
