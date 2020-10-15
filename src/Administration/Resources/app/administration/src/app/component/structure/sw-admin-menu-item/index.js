import template from './sw-admin-menu-item.html.twig';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-admin-menu-item', {
    template,

    inject: ['acl'],

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
        },

        showMenuItem() {
            // special case for settings module, children are stored in a global state store
            if (this.entry.path === 'sw.settings.index') {
                return this.acl.hasActiveSettingModules();
            }
            if (this.children.length > 0) {
                return true;
            }

            if (this.getLinkToProp && this.getLinkToProp.name) {
                const { name } = this.getLinkToProp;

                return this.hasAccessToRoute(name);
            }

            return false;
        },

        entryPath() {
            if (this.entry.path && this.hasAccessToRoute(this.entry.path)) {
                return this.entry.path;
            }

            return undefined;
        },

        children() {
            return this.entry.children.filter(child => {
                if (!child.privilege) {
                    return true;
                }

                return this.acl.can(child.privilege);
            });
        }
    },

    methods: {
        hasAccessToRoute(path) {
            const route = path.replace(/\./g, '/');
            const match = this.$router.match(route);

            if (!match.meta) {
                return true;
            }

            return this.acl.can(match.meta.privilege);
        },

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
