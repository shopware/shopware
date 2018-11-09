import { Component } from 'src/core/shopware';
import template from './sw-sidebar.html.twig';
import './sw-sidebar.less';

Component.register('sw-sidebar', {
    template,

    props: {
        propagateWidth: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            items: []
        };
    },

    mounted() {
        this.mountedComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    computed: {
        sections() {
            const sections = {};
            this.items.forEach((item) => {
                if (!sections[item.position]) {
                    sections[item.position] = [];
                }
                sections[item.position].push(item);
            });

            return sections;
        }
    },

    methods: {
        mountedComponent() {
            if (this.propagateWidth) {
                const sidebarWidth = this.$el.querySelector('.sw-sidebar__navigation').offsetWidth;

                this.$root.$emit('swSidebarMounted', sidebarWidth);
            }
        },

        destroyedComponent() {
            if (this.propagateWidth) {
                this.$root.$emit('swSidebarDestroyed');
            }
        },

        _isItemRegistered(itemToCheck) {
            const index = this.items.findIndex((item) => {
                return item === itemToCheck;
            });
            return index > -1;
        },

        registerSidebarItem(item) {
            if (this._isItemRegistered(item)) {
                return;
            }

            this.items.push(item);

            this.$on('sw-sidebar-navigation-item-clicked', item.sidebarButtonClick);
            item.$on('sw-sidebar-item-toggle-active', this.setItemActive);
        },

        setItemActive(clickedItem) {
            this.$emit('sw-sidebar-navigation-item-clicked', clickedItem);
        }
    }
});
