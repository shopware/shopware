import { Component } from 'src/core/shopware';
import template from './sw-admin-menu-item.html.twig';

/**
 * @private
 */
Component.register('sw-admin-menu-item', {
    template,

    props: {
        entry: {
            type: Object,
            required: true
        },
        displayIcon: {
            type: Boolean,
            default: true,
            required: false
        },
        iconSize: {
            type: String,
            default: '20px',
            required: false
        },
        collapsibleText: {
            type: Boolean,
            default: true,
            required: false
        },
        sidebarExpanded: {
            type: Boolean,
            default: true,
            required: false
        }
    },

    computed: {
        getLinkToProp() {
            if (this.entry.params) {
                return { name: this.entry.path, params: this.entry.params };
            }

            return { name: this.entry.path };
        },

        getEntryLabel() {
            if (this.entry.label instanceof Object) {
                return (this.entry.label.translated) ? this.entry.label.label : this.$tc(this.entry.label.label);
            }
            return this.$tc(this.entry.label);
        }
    },

    methods: {
        getIconName(name) {
            return `${name}`;
        },

        getItemName(menuItemName) {
            return menuItemName.replace(/\./g, '-');
        }
    }
});
