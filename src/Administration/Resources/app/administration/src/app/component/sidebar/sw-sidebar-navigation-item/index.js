import template from './sw-sidebar-navigation-item.html.twig';
import './sw-sidebar-navigation-item.scss';

/**
 * @sw-package framework
 *
 * @private
 */
export default {
    template,

    emits: ['item-click'],

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
};
