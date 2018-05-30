import { Component } from 'src/core/shopware';
import { hasOwnProperty } from 'src/core/service/utils/object.utils';
import template from './sw-admin-menu-item.html.twig';

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

    data() {
        return {
            startupRouteParent: ''
        };
    },

    computed: {
        startupClass() {
            return {
                'sw-admin-menu__navigation-link-active': (this.startupRouteParent === this.entry.id ||
                    this.startupRouteParent === this.entry.path)
            };
        }
    },

    created() {
        if (hasOwnProperty(this.$route.meta, '$current')) {
            this.startupRouteParent = this.$route.meta.$current.parent;
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
