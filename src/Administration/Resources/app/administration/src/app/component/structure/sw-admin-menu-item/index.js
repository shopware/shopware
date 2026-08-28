import { MtCollapsible, MtCollapsibleContent, MtCollapsibleTrigger } from '@shopware-ag/meteor-component-library';
import useModuleIconColors from 'src/app/composables/use-module-icon-colors';
import template from './sw-admin-menu-item.html.twig';
import { getActiveRouteNames, isEntryOnActiveRoute, entryParamsMatchRoute } from './menu-item-active.helper';
import './sw-admin-menu-item.scss';

/**
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
        'flyout-navigate',
        'navigation-link-click',
    ],

    props: {
        entry: {
            type: Object,
            required: true,
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
        showActiveState: {
            type: Boolean,
            default: true,
            required: false,
        },
        flyoutActive: {
            type: Boolean,
            default: false,
            required: false,
        },
    },

    data() {
        return {
            suppressRouteKeepsFolderOpen: false,
            manualNestedOpen: false,
        };
    },

    computed: {
        isLeafDepth() {
            // Admin menu supports at most three levels; level-3 rows are leaf items only
            return this.menuDepth >= 3;
        },

        activeRouteNames() {
            return getActiveRouteNames(this.$route, this.$router);
        },

        aclFilteredEntry() {
            // The entry with its children reduced to the ones the user may see.
            return { ...this.entry, children: this.children };
        },

        hasActiveChild() {
            return this.children.some((child) => isEntryOnActiveRoute(child, this.$route, this.activeRouteNames));
        },

        rowActive() {
            if (!this.showActiveState) {
                return false;
            }

            if (!isEntryOnActiveRoute(this.aclFilteredEntry, this.$route, this.activeRouteNames)) {
                return false;
            }

            const selfIsCurrent =
                !this.hasActiveChild &&
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
            // Ignores the sidebar state on purpose — switching template branch on collapse makes the icons flash
            return this.children.length > 0 && !this.isLeafDepth;
        },

        routeKeepsFolderOpen() {
            if (!this.children.length || this.suppressRouteKeepsFolderOpen) {
                return false;
            }

            return this.hasActiveChild;
        },

        submenuVisuallyOpen() {
            if (this.menuDepth === 1) {
                if (!this.sidebarExpanded) {
                    return false;
                }

                const hasExpandedBranches = Shopware.Store.get('adminMenu').expandedEntries.length > 0;

                return hasExpandedBranches ? this.isExpanded : this.isExpanded || this.routeKeepsFolderOpen;
            }

            return this.routeKeepsFolderOpen || this.manualNestedOpen;
        },

        collapsibleOpen() {
            return this.hasCollapsibleSubtree && this.submenuVisuallyOpen;
        },

        expandIcon() {
            return this.submenuVisuallyOpen ? 'regular-chevron-up-xs' : 'regular-chevron-down-xs';
        },

        collapsibleLiClass() {
            return [
                'sw-admin-menu__navigation-list-item',
                this.getElementClasses(this.entry.id || this.entryPath),
                {
                    'is--entry-expanded': this.collapsibleOpen,
                    'is--child-active': this.childRouteActive,
                    'is--flyout-enabled': this.flyoutActive,
                    'is--module-colored': !!this.navigationIconColor,
                },
            ];
        },

        leafLiClass() {
            return [
                'sw-admin-menu__navigation-list-item',
                this.getElementClasses(this.entry.id || this.entryPath),
                {
                    'is--entry-expanded': this.submenuVisuallyOpen,
                    'is--child-active': this.childRouteActive,
                    'is--module-colored': !!this.navigationIconColor,
                },
            ];
        },

        navigationIconName() {
            const isActive = this.rowActive || this.childRouteActive;

            return this.getIconName(this.entry.icon, isActive);
        },

        navigationIconColor() {
            // Undefined leaves the icon to the stylesheet, which also owns the active state color
            return useModuleIconColors().enabled.value ? this.entry.color : undefined;
        },

        moduleColorStyle() {
            // Inherited by the sub items, which mark their active state with the parent module color
            return this.navigationIconColor ? { '--sw-admin-menu-module-color': this.navigationIconColor } : null;
        },

        childRouteActive() {
            return this.children.length > 0 && this.submenuVisuallyOpen && this.hasActiveChild;
        },

        collapsedFlyoutAria() {
            if (this.sidebarExpanded || this.menuDepth !== 1 || this.children.length === 0) {
                return {};
            }

            // aria-controls only while open
            if (!this.flyoutActive) {
                return { 'aria-expanded': 'false' };
            }

            return {
                'aria-expanded': 'true',
                'aria-controls': 'sw-admin-menu-flyout',
            };
        },

        collapsedAriaLabel() {
            // Collapsed top-level rows hide their label, so the accessible name needs an aria-label.
            return !this.sidebarExpanded && this.menuDepth === 1 ? this.getEntryLabel : null;
        },

        routerLinkActiveClass() {
            return this.showActiveState ? 'router-link-active' : '';
        },

        routerLinkExactActiveClass() {
            return this.showActiveState ? 'router-link-exact-active' : '';
        },

        showsCollapsedTooltip() {
            // Top-level entries without children have no flyout, label is accessible via a tooltip
            return !this.sidebarExpanded && this.menuDepth === 1 && this.children.length === 0;
        },
    },

    watch: {
        // Query-insensitive on purpose: listing pagination/sorting must not undo a manual collapse
        '$route.path'() {
            this.suppressRouteKeepsFolderOpen = false;
        },
    },

    methods: {
        collapsedTooltipTriggerProps(tooltipProps) {
            if (this.showsCollapsedTooltip) {
                // Focus does not bubble to the non-focusable row, focusin/focusout do
                const { onFocus, onBlur, ...bubblingProps } = tooltipProps;

                return { ...bubblingProps, onFocusin: onFocus, onFocusout: onBlur };
            }

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
                    'navigation-list-item--nested': this.menuDepth > 1,
                },
            ];
        },

        toggleSubmenu() {
            if (!this.hasCollapsibleSubtree) {
                return;
            }

            this.onCollapsibleOpenUpdate(!this.collapsibleOpen);
        },

        onNavigationLinkClick() {
            if (!this.sidebarExpanded) {
                this.$emit('flyout-navigate', { disclosesChildren: this.hasCollapsibleSubtree });
            }

            // No-op unless this row has a collapsible subtree.
            this.toggleSubmenu();

            this.$emit('navigation-link-click');
        },

        forwardNavigationLinkClick() {
            this.$emit('navigation-link-click');
        },

        forwardFlyoutNavigate(payload) {
            this.$emit('flyout-navigate', payload);
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

        onCollapsedParentKeydown(event) {
            // Keyboard access to the collapsed flyout - disclosure navigation pattern
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
    },
};
