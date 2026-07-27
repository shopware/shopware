import { MtCollapsible, MtCollapsibleContent, MtCollapsibleTrigger } from '@shopware-ag/meteor-component-library';
import template from './sw-admin-menu-item.html.twig';
import { getActiveRouteNames, isEntryOnActiveRoute, entryParamsMatchRoute } from './menu-item-active.helper';
import './sw-admin-menu-item.scss';

/**
 * The mt-tooltip trigger props that make the tooltip open. Stripped from the trigger when the
 * collapsed tooltip must stay silent — see collapsedTooltipTriggerProps.
 *
 * @private
 */
export const TOOLTIP_OPEN_TRIGGER_PROPS = [
    'onMouseover',
    'onFocus',
    'aria-describedby',
];

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

    inject: [
        'acl',
        'feature',
    ],

    emits: [
        'menu-item-hover',
        'branch-toggle',
        'flyout-focus-request',
        'flyout-close-request',
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
        /** Set to false for entries that must never show the "current page" highlight. */
        showActiveState: {
            type: Boolean,
            default: true,
            required: false,
        },
        /** Whether the collapsed sidebar currently shows this entry's children in the flyout. */
        flyoutActive: {
            type: Boolean,
            default: false,
            required: false,
        },
    },

    computed: {
        /** Admin menu supports at most three levels; level-3 rows are leaf items only */
        isLeafDepth() {
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
            if (!this.showActiveState) {
                return false;
            }

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
            return this.children.length > 0 && !this.isLeafDepth;
        },

        // Keep collapsible items on the same <mt-collapsible> template branch whether the sidebar
        // is expanded or collapsed. Switching branches on collapse remounts the row and makes the
        // navigation icons flash; the collapsed appearance is handled purely via CSS and the
        // forced-closed collapsibleOpen state instead.
        usesCollapsible() {
            return this.hasCollapsibleSubtree;
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

        leafLiClass() {
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

        /**
         * Collapsed top-level parents act as disclosure triggers for the flyout:
         * aria-expanded reflects the flyout state instead of the (forced closed)
         * collapsible. Empty while expanded, so the collapsible semantics apply.
         */
        collapsedFlyoutAria() {
            if (this.sidebarExpanded || this.menuDepth !== 1 || this.children.length === 0) {
                return {};
            }

            return {
                'aria-expanded': this.flyoutActive ? 'true' : 'false',
                ...(this.flyoutActive ? { 'aria-controls': 'sw-admin-menu-flyout' } : {}),
            };
        },

        /**
         * Vue Router adds its active classes on matching links by itself, so they
         * must be suppressed explicitly when the active state is disabled.
         */
        routerLinkActiveClass() {
            return this.showActiveState ? 'router-link-active' : '';
        },

        routerLinkExactActiveClass() {
            return this.showActiveState ? 'router-link-exact-active' : '';
        },

        /**
         * Top-level entries without children have no flyout, so their label is only
         * accessible via a tooltip while the sidebar is collapsed.
         */
        showsCollapsedTooltip() {
            return !this.sidebarExpanded && this.menuDepth === 1 && this.children.length === 0;
        },
    },

    methods: {
        collapsedTooltipTriggerProps(tooltipProps) {
            if (this.showsCollapsedTooltip) {
                return tooltipProps;
            }

            // The wrapping <mt-tooltip> stays mounted even when the tooltip must not open,
            // because unmounting the row on sidebar toggle makes the navigation icons flash
            // (see usesCollapsible). The trigger id also has to stay bound; mt-tooltip errors
            // when it cannot find its trigger element. Only the handlers that open the
            // tooltip are dropped — the closing handlers keep an already visible tooltip
            // hidable when the sidebar expands while it is hovered.
            //
            // These are mt-tooltip's internal trigger prop names rather than a documented API:
            // a meteor upgrade that renames or adds an opening handler would silently re-enable
            // the tooltip. TOOLTIP_OPEN_TRIGGER_PROPS is asserted against the real component in
            // sw-admin-menu-item.spec/collapsed-sidebar.spec.js so the drift fails a test.
            return Object.fromEntries(
                Object.entries(tooltipProps).filter(([key]) => !TOOLTIP_OPEN_TRIGGER_PROPS.includes(key)),
            );
        },

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
            this.suppressRouteKeepsFolderOpen = !open;

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

        /**
         * Keyboard access to the collapsed flyout (disclosure navigation pattern):
         * ArrowRight — and Enter/Space on entries without an own route — opens the
         * flyout and moves focus into it; Escape or ArrowLeft closes an open flyout.
         */
        onCollapsedParentKeydown(event) {
            if (this.sidebarExpanded || this.menuDepth !== 1 || this.children.length === 0) {
                return;
            }

            if ((event.key === 'Escape' || event.key === 'ArrowLeft') && this.flyoutActive) {
                this.$emit('flyout-close-request');
                return;
            }

            const isActivationKey = event.key === 'Enter' || event.key === ' ';
            // Entries with an own route keep Enter/Space for navigation.
            const opensFlyout = event.key === 'ArrowRight' || (isActivationKey && !this.entryPath);

            if (!opensFlyout) {
                return;
            }

            event.preventDefault();
            this.$emit('menu-item-hover', this.entry, event.currentTarget);
            this.$emit('flyout-focus-request');
        },

        forwardMenuItemHover(entry, target) {
            this.$emit('menu-item-hover', entry, target);
        },

        forwardBranchToggle(payload) {
            this.$emit('branch-toggle', payload);
        },
    },
};
