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

        tooltipContent() {
            if (!this.sidebarItem.tooltipShortcut?.length) {
                return this.sidebarItem.title;
            }

            const shortcutKeys = this.sidebarItem.tooltipShortcut.map((key) => {
                return `<b class="sw-sidebar-navigation-item__tooltip-shortcut-key" aria-label="${key}">${key}</b>`;
            });

            return [
                `<b class="sw-sidebar-navigation-item__tooltip-title">${this.sidebarItem.title}</b>`,
                shortcutKeys.join(' '),
            ].join(' ');
        },
    },

    methods: {
        emitButtonClicked() {
            this.$emit('item-click', this.sidebarItem);
        },
    },
};
