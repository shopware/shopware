import template from './sw-sidebar-navigation-item.html.twig';
import './sw-sidebar-navigation-item.scss';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 */
Component.register('sw-sidebar-navigation-item', {
    template,

    props: {
        sidebarItem: {
            type: Object,
            required: true,
        },
    },

    computed: {
        badgeTypeClasses() {
            return [
                `is--${this.sidebarItem.badgeType}`,
            ];
        },
    },

    methods: {
        emitButtonClicked() {
            this.$emit('item-click', this.sidebarItem);
        },
    },
});
