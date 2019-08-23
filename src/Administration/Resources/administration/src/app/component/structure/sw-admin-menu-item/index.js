import template from './sw-admin-menu-item.html.twig';

const { Component } = Shopware;

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
        parentEntries: {
            type: Array,
            required: false
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
        },

        subIsActive(path) {
            const meta = this.$route.meta;
            let compareTo;

            if (meta.$current) {
                compareTo = meta.$current.parent;
            }
            if (meta.parentPath) {
                compareTo = meta.parentPath;
            }

            if (meta.$module) {
                if (meta.$module.navigation && meta.$module.navigation[0].parent) {
                    compareTo = meta.$module.navigation[0].parent;
                }
            }

            if (this.entry.path) {
                return compareTo ? compareTo.replace(/-/g, '.').indexOf(path.replace(/\.index/g, '')) === 0 : false;
            }

            return this.entry.id === compareTo;
        }
    }
});
