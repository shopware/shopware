import template from './sw-admin-menu-item.html.twig';

const { Component } = Shopware;
const { createId, types } = Shopware.Utils;

/**
 * @package admin
 *
 * @private
 */
Component.register('sw-admin-menu-item', {
    template,

    inject: ['acl'],

    props: {
        entry: {
            type: Object,
            required: true,
        },
        parentEntries: {
            type: Array,
            required: false,
            default: () => [],
        },
        displayIcon: {
            type: Boolean,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
            required: false,
        },
        iconSize: {
            type: String,
            default: '20px',
            required: false,
        },
        collapsibleText: {
            type: Boolean,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
            required: false,
        },
        sidebarExpanded: {
            type: Boolean,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
            required: false,
        },
        borderColor: {
            type: String,
            default: '#333',
            required: false,
        },
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
        },
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

        subIsActive(path, entryId) {
            // this is an extra case for the sw-sales-channel menu, without this all sales-channels
            // would have the selection highlight as soon as one is selected.
            if (this.$route.name?.startsWith('sw.sales.channel.') && entryId) {
                return this.$route.params?.id === entryId;
            }

            const meta = this.$route.meta;
            const adminMenuEntries = Shopware.State.get('adminMenu').adminModuleNavigation;
            let compareTo;

            function findRootEntry(currentPath, foundPaths = []) {
                const foundEntry = adminMenuEntries.find((entry) => {
                    return entry.path === currentPath || entry.id === currentPath;
                });

                foundPaths.push(foundEntry.path || foundEntry.id);

                if (foundEntry.parent?.length) {
                    return findRootEntry(foundEntry.parent, foundPaths);
                }

                return foundPaths;
            }

            if (meta.$current) {
                const matchingPaths = findRootEntry(meta.$current.path);
                return matchingPaths.includes(path);
            }

            if (meta.parentPath) {
                compareTo = meta.parentPath;
            }

            if (meta.$module?.navigation?.[0].parent) {
                compareTo = meta.$module.navigation[0].parent;
            }

            if (!compareTo) {
                compareTo = this.$route?.name;
            }

            if (this.entry.path) {
                return compareTo ? compareTo.replace(/-/g, '.').indexOf(path.replace(/\.index/g, '')) === 0 : false;
            }

            return this.entry.id === compareTo;
        },

        getElementClasses(menuItemName) {
            const name = menuItemName.replace(/\./g, '-');
            const hasChildren = this.entry.children.length > 0;
            const convertName = this.entry.id || this.entry.path;
            const convertedId = convertName.replace(/\./g, '-');

            return [
                convertedId,
                `navigation-list-item__type-${this.entry.moduleType}`,
                `navigation-list-item__${name}`,
                `sw-admin-menu__item--${this.entry.id}`,
                `navigation-list-item__level-${this.entry.level}`,
                { 'navigation-list-item__has-children': hasChildren },
            ];
        },

        onSubMenuItemEnter(entry, $event, parentEntries) {
            this.$emit('sub-menu-item-enter', entry, $event, parentEntries);
        },

        isFirstPluginInMenuEntries(entry, menuEntries) {
            const firstPluginEntry = menuEntries.find((menuEntry) => {
                return menuEntry.moduleType === 'plugin';
            });

            if (!firstPluginEntry) {
                return false;
            }
            return types.isEqual(entry, firstPluginEntry);
        },

        getCustomKey(path) {
            return `${path}-${createId()}`;
        },
    },
});
