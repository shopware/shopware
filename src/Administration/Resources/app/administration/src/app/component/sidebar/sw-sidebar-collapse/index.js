import template from './sw-sidebar-collapse.html.twig';
import './sw-sidebar-collaps.scss';

const { Component } = Shopware;

Component.extend('sw-sidebar-collapse', 'sw-collapse', {
    template,

    computed: {
        expandButtonClass() {
            return {
                'is--hidden': this.expanded,
            };
        },

        collapseButtonClass() {
            return {
                'is--hidden': !this.expanded,
            };
        },
    },

    methods: {
        collapseItem() {
            this.$super('collapseItem');
            this.$emit('change-expanded', { isExpanded: this.expanded });
        },
    },
});
