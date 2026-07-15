import { MtCollapsible, MtCollapsibleContent, MtCollapsibleTrigger } from '@shopware-ag/meteor-component-library';
import template from './sw-admin-menu-item.html.twig';
import { getActiveRouteNames, isEntryOnActiveRoute, entryParamsMatchRoute } from './menu-item-active.helper';
import './sw-admin-menu-item.scss';

/**
 * @sw-package framework
 *
 * @private
 */
export default {
    template,

    components: {
        MtCollapsible,
        MtCollapsibleTrigger,
        MtCollapsibleContent,
    },

    inject: {
        acl: 'acl',
        feature: 'feature',
    },

    emits: [
        'menu-item-click',
        'menu-item-hover',
        'branch-toggle',
    ],

    data() {
        return {
            suppressRouteKeepsFolderOpen: false,
            manualNestedOpen: false,
        };
    },

    watch: {
        '$route.fullPath'() {
            this.suppressRouteKeepsFolderOpen = false;
        },
    },

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

        menuDepth: {
            type: Number,
            required: false,
            default: 1,
            validator: (v) =>
                [
                    1,
                    2,
                    3,
                ].includes(v),
        },

        displayIcon: {
            type: Boolean,
            default: true,
            required: false,
        },
        iconSize: {
            type: String,
            default: '16px',
            required: false,
        },
        collapsibleText: {
            type: Boolean,
            default: true,
            required: false,
        },
        sidebarExpanded: {
            type: Boolean,
            default: true,
            required: false,
        },
        isExpanded: {
            type: Boolean,
            default: false,
            required: false,
        },
    },

    computed: {
        /** Admin menu supports at most three levels; level-3 rows are leaf items only */
        leafDepth() {
            return this.menuDepth >= 3;
        },

        /** Route names that count as "current": the resolved chain + the parentPath bridge. */
        activeRouteNames() {
            return getActiveRouteNames(this.$route, this.$router);
        },

        /**
         * Whether this row should show the "current page" highlight (`router-link-active`).
         * True when the entry (or a descendant) is on the active route, except that an open
         * parent yields the highlight to its active child.
         */
        rowActive() {
            if (!isEntryOnActiveRoute(this.entry, this.$route, this.activeRouteNames)) {
                return false;
            }

            const activeChild = this.children.some((child) =>
                isEntryOnActiveRoute(child, this.$route, this.activeRouteNames),
            );
            const selfIsCurrent =
                !activeChild &&
                !!this.entry.path &&
                this.activeRouteNames.has(this.entry.path) &&
                entryParamsMatchRoute(this.entry, this.$route);

            if (!selfIsCurrent && this.children.length > 0 && this.submenuVisuallyOpen) {
                return false;
            }

            return true;
        },

        getLinkToProp() {
            if (this.entry.params) {
                return { name: this.entry.path, params: this.entry.params };
            }

            return { name: this.entry.path };
        },

        getEntryLabel() {
            if (this.entry.label instanceof Object) {
                return this.entry.label.translated ? this.entry.label.label : this.$t(this.entry.label.label);
            }
            return this.$t(this.entry.label);
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
            return this.entry.children.filter((child) => {
                if (!child.privilege) {
                    return true;
                }

                return this.acl.can(child.privilege);
            });
        },

        hasCollapsibleSubtree() {
            return this.children.length > 0 && !this.leafDepth;
        },

        usesCollapsible() {
            if (!this.hasCollapsibleSubtree) {
                return false;
            }

            // Keep collapsible items on the same <MtCollapsible> template branch whether the sidebar
            // is expanded or collapsed. Switching branches on collapse remounts the row and makes the
            // navigation icons flash; the collapsed appearance is handled purely via CSS and the
            // forced-closed collapsibleOpen state instead.
            if (!this.sidebarExpanded && this.menuDepth >= 2) {
                return true;
            }

            return this.menuDepth <= 2;
        },

        routeKeepsFolderOpen() {
            if (!this.sidebarExpanded || !this.children.length || this.entry.path || this.suppressRouteKeepsFolderOpen) {
                return false;
            }

            return this.children.some((child) => isEntryOnActiveRoute(child, this.$route, this.activeRouteNames));
        },

        submenuVisuallyOpen() {
            if (!this.sidebarExpanded && this.menuDepth === 1) {
                return false;
            }

            if (this.menuDepth === 1) {
                const expandedBranches = Shopware.Store.get('adminMenu').expandedEntries ?? [];
                const myKey = this.entry.id ?? this.entry.path;

                if (expandedBranches.length > 0 && myKey !== undefined && myKey !== '') {
                    const expandedKeys = expandedBranches.map((e) => e?.id ?? e?.path);

                    if (!expandedKeys.includes(myKey)) {
                        return false;
                    }
                }

                return this.isExpanded || this.routeKeepsFolderOpen;
            }

            return this.routeKeepsFolderOpen || this.manualNestedOpen;
        },

        collapsibleOpen() {
            if (!this.usesCollapsible) {
                return false;
            }

            return this.submenuVisuallyOpen;
        },

        expandIcon() {
            const expanded = this.usesCollapsible ? this.collapsibleOpen : this.submenuVisuallyOpen;

            return expanded ? 'regular-chevron-up-xs' : 'regular-chevron-down-xs';
        },

        collapsibleLiClass() {
            return [
                'sw-admin-menu__navigation-list-item',
                this.getElementClasses(this.entry.id || this.entryPath),
                { 'is--entry-expanded': this.collapsibleOpen, 'is--child-active': this.childRouteActive },
            ];
        },

        legacyLiClass() {
            return [
                'sw-admin-menu__navigation-list-item',
                this.getElementClasses(this.entry.id || this.entryPath),
                { 'is--entry-expanded': this.submenuVisuallyOpen, 'is--child-active': this.childRouteActive },
            ];
        },

        navigationIconName() {
            const isActive = this.rowActive || this.childRouteActive;

            return this.getIconName(this.entry.icon, isActive);
        },

        childRouteActive() {
            if (this.children.length === 0 || !this.submenuVisuallyOpen) {
                return false;
            }

            // A descendant's route is the current route (or an ancestor of it).
            return this.children.some((child) => isEntryOnActiveRoute(child, this.$route, this.activeRouteNames));
        },
    },

    methods: {
        hasAccessToRoute(path) {
            const match = this.$router.getRoutes().find((route) => route.name === path);

            if (!match?.meta) {
                return true;
            }

            return this.acl.can(match.meta.privilege);
        },

        getIconName(name, isActive = false) {
            if (isActive && typeof name === 'string') {
                if (name.startsWith('regular-')) {
                    return name.replace('regular-', 'solid-');
                }

                if (name.startsWith('icon/regular/')) {
                    return name.replace('icon/regular/', 'icon/solid/');
                }
            }

            return `${name}`;
        },

        getItemName(menuItemName) {
            return menuItemName.replace(/\./g, '-');
        },

        getElementClasses(menuItemName) {
            const name = menuItemName.replace(/\./g, '-');
            const hasChildren = this.children.length > 0;
            const convertName = this.entry.id || this.entry.path;
            const convertedId = convertName.replace(/\./g, '-');

            return [
                convertedId,
                `navigation-list-item__type-${this.entry.moduleType}`,
                `navigation-list-item__${name}`,
                `sw-admin-menu__item--${this.entry.id}`,
                `navigation-list-item__level-${this.entry.level}`,
                {
                    'navigation-list-item__has-children': hasChildren,
                    'navigation-list-item--nested': this.entry.level > 1,
                },
            ];
        },

        getStableSubMenuItemKey(entry, index) {
            if (entry.id) {
                return String(entry.id);
            }

            if (entry.path) {
                return String(entry.path);
            }

            return `admin-menu-sub-${index}`;
        },

        toggleSubmenu() {
            if (!this.usesCollapsible) {
                return;
            }

            this.onCollapsibleOpenUpdate(!this.collapsibleOpen);
        },

        onCollapsibleOpenUpdate(open) {
            if (!open) {
                this.suppressRouteKeepsFolderOpen = true;
            } else {
                this.suppressRouteKeepsFolderOpen = false;
            }

            if (this.menuDepth >= 2) {
                this.manualNestedOpen = open;
            }

            if (this.menuDepth === 1 && this.sidebarExpanded) {
                this.$emit('branch-toggle', {
                    entry: this.entry,
                    open,
                });
            }
        },

        emitMenuInteractionForFlyoutDismiss(event) {
            this.$emit('menu-item-click', this.entry, event?.currentTarget || event?.target);
        },

        forwardMenuItemHover(entry, target) {
            this.$emit('menu-item-hover', entry, target);
        },

        forwardMenuItemClick(entry, target) {
            this.$emit('menu-item-click', entry, target);
        },

        forwardBranchToggle(payload) {
            this.$emit('branch-toggle', payload);
        },

        handleLegacyRowClick(event) {
            const isNestedRow = !this.$el?.parentElement?.classList.contains('sw-admin-menu__navigation-list');

            if (isNestedRow) {
                event.stopPropagation();
            }

            this.$emit('menu-item-click', this.entry, event.target);
        },
    },
};
