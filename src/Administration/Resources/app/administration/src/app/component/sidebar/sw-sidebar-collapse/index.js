/**
 * @package admin
 */

import template from './sw-sidebar-collapse.html.twig';
import './sw-sidebar-collapse.scss';

const { Component } = Shopware;

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
Component.extend('sw-sidebar-collapse', 'sw-collapse', {
    template,

    props: {
        expandChevronDirection: {
            type: String,
            required: false,
            default: 'right',
            validator: (value) => ['up', 'left', 'right', 'bottom'].includes(value),
        },
    },

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
