import { createFocusTrap } from 'focus-trap';
import template from './sw-admin-menu.html.twig';
import { getActiveRouteNames, isEntryOnActiveRoute } from '../sw-admin-menu-item/menu-item-active.helper';
import './sw-admin-menu.scss';

const { Mixin } = Shopware;
const { dom } = Shopware.Utils;

// Keep in sync with --sw-admin-menu-duration in sw-admin-menu.scss.
const SIDEBAR_TOGGLE_ANIMATION_DURATION = 500;

/**
 * @sw-package framework
 *
 * @private
 */
export default {
    template,

    inject: [
        'acl',
        'menuService',
        'loginService',
        'userService',
        'appModulesService',
        'feature',
        'customEntityDefinitionService',
        'systemConfigApiService',
        'shortcutService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    shortcuts: {
        s: 'onToggleSidebarShortcut',
    },

    data() {
        return {
            activeEntry: null,
            isOffCanvasShown: false,
            isUserActionsActive: false,
            flyoutEntries: [],
            flyoutTitle: '',
            flyoutCloseTimeoutId: null,
            isFlyoutClosing: false,

            scrollbarOffset: '',
            isUserLoading: true,
            flyoutReferenceElement: null,
            viewportWidth: null,
            shopName: '',
            isTogglingSidebar: false,
            toggleSidebarTimeout: null,
            // Key of the top-level branch owning the currently active route.
            activeBranchKey: null,
        };
    },

    computed: {
        currentUser() {
            return Shopware.Store.get('session').currentUser;
        },

        isExpanded() {
            // Below the mobile breakpoint the menu is shown as an off-canvas panel and is never
            // collapsible, so it always renders in its expanded form regardless of the stored state.
            return this.adminMenuStore.isExpanded || this.isMobileViewport;
        },

        isMobileViewport() {
            return this.viewportWidth !== null && this.viewportWidth <= 1280;
        },

        userTitle() {
            if (this.currentUser && this.currentUser.admin) {
                return this.$t('global.sw-admin-menu.administrator');
            }

            if (this.currentUser && this.currentUser.title && this.currentUser.title.length > 0) {
                return this.currentUser.title;
            }

            if (this.currentUser && this.currentUser.aclRoles && this.currentUser.aclRoles.length > 0) {
                return this.currentUser.aclRoles[0].name;
            }

            if (this.currentUser && this.currentUser.title) {
                return this.currentUser.title;
            }

            return '';
        },

        currentLocale() {
            return Shopware.Store.get('session').currentLocale;
        },

        adminModuleNavigation() {
            const adminModuleNavigationEntries = this.adminMenuStore.adminModuleNavigation;

            // Throw an console error if navigation entry is on level 4 or higher. Also remove the navigation entry from menu
            return adminModuleNavigationEntries.filter((entry) => {
                const levelOneParent = adminModuleNavigationEntries.find((e) => entry.parent && e.id === entry.parent);

                const levelTwoParent = adminModuleNavigationEntries.find(
                    (e) => levelOneParent?.parent && e.id === levelOneParent?.parent,
                );

                const levelThreeParent = adminModuleNavigationEntries.find(
                    (e) => levelTwoParent?.parent && e.id === levelTwoParent?.parent,
                );

                if (levelThreeParent) {
                    Shopware.Utils.debug.error(
                        new Error(
                            `The navigation entry "${entry.id}" is nested on level 4 or higher.\
The admin menu only supports up to three levels of nesting.`,
                        ),
                    );

                    return false;
                }

                return true;
            });
        },

        appModuleNavigation() {
            return this.adminMenuStore.appModuleNavigation;
        },

        navigationEntries() {
            return [
                ...this.adminModuleNavigation,
                ...this.appModuleNavigation,
                ...this.extensionModuleNavigation,
                ...this.customEntityDefinitionService.getMenuEntries(),
            ];
        },

        mainMenuEntries() {
            const tree = new Shopware.Helper.FlatTreeHelper((first, second) => first.position - second.position);

            this.navigationEntries.forEach((module) => tree.add(module));

            return tree.convertToTree();
        },

        scrollbarOffsetStyle() {
            return {
                right: this.scrollbarOffset,
                'margin-left': this.scrollbarOffset,
            };
        },

        adminMenuClasses() {
            return {
                'is--expanded': this.isExpanded,
                'is--collapsed': !this.isExpanded,
                'is--off-canvas-shown': this.isOffCanvasShown,
                'is--toggling': this.isTogglingSidebar,
            };
        },

        userName() {
            if (!this.currentUser) {
                return '';
            }

            return `${this.currentUser.firstName} ${this.currentUser.lastName}`;
        },

        avatarUrl() {
            if (this.currentUser && this.currentUser.avatarMedia) {
                return this.currentUser.avatarMedia.url;
            }

            return null;
        },

        firstName() {
            return this.currentUser ? this.currentUser.firstName : '';
        },

        lastName() {
            return this.currentUser ? this.currentUser.lastName : '';
        },

        extensionMenuItems() {
            return Shopware.Store.get('menuItem').menuItems;
        },

        extensionModuleNavigation() {
            return this.extensionMenuItems.map((extensionMenuItem) => {
                return {
                    id: Shopware.Utils.createId(),
                    label: extensionMenuItem.label,
                    position: extensionMenuItem.position ?? 110,
                    parent: extensionMenuItem.parent ?? 'sw-extension',
                    moduleType: 'plugin',
                    path: 'sw.extension.sdk.index',
                    params: {
                        id: extensionMenuItem.moduleId,
                    },
                };
            });
        },

        adminMenuStore() {
            return Shopware.Store.get('adminMenu');
        },
    },

    watch: {
        isExpanded() {
            this.toggleSidebar();
            this.startSidebarToggleWindow();
        },
        '$route.fullPath': {
            handler() {
                // Close an open flyout after navigating, e.g. when a flyout link
                // was activated. Focus follows the navigation, so it must not be
                // pulled back into the sidebar.
                if (!this.isExpanded && this.flyoutEntries.length) {
                    this.deactivateFlyoutFocusTrap(false);
                    this.onFlyoutLeave();
                }

                if (this.isMobileViewport && this.isOffCanvasShown) {
                    this.closeOffCanvas();
                }

                this.$nextTick(() => this.expandAncestorBranchesForCurrentRoute());
            },
            immediate: true,
        },
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    beforeUnmount() {
        this.cancelFlyoutClose();
        this.deactivateFlyoutFocusTrap(false);

        this.beforeUnmountedComponent();
    },

    methods: {
        createdComponent() {
            // Non-reactive instance property; holds the active flyout focus trap.
            this.flyoutFocusTrap = null;

            this.loginService.notifyOnLoginListener();

            this.viewportWidth = this.$device.getViewportWidth();
            this.getUser();
            this.loadShopName();

            Shopware.Utils.EventBus.on('sw-admin-menu/toggle-offcanvas', this.onToggleCanvas);

            this.initNavigation();
        },

        loadShopName() {
            this.systemConfigApiService
                .getValues('core.basicInformation')
                .then((values) => {
                    this.shopName = values['core.basicInformation.shopName'] || 'Shopware';
                })
                .catch(() => {
                    // Users without system config read permission still get the fallback.
                    this.shopName = 'Shopware';
                });
        },

        beforeUnmountedComponent() {
            Shopware.Utils.EventBus.off('sw-admin-menu/toggle-offcanvas', this.onToggleCanvas);

            if (this.toggleSidebarTimeout) {
                clearTimeout(this.toggleSidebarTimeout);
            }
        },

        onToggleCanvas(state) {
            this.isOffCanvasShown = state;
        },

        closeOffCanvas() {
            this.isOffCanvasShown = false;
            Shopware.Utils.EventBus.emit('sw-admin-menu/toggle-offcanvas', false);
        },

        initNavigation() {
            this.adminMenuStore.adminModuleNavigation = this.menuService.getNavigationFromAdminModules();

            this.refreshApps();
        },

        refreshApps() {
            return this.appModulesService.fetchAppModules().then((modules) => {
                Shopware.Store.get('shopwareApps').apps = modules;
            });
        },

        collapseAdminMenu() {
            this.adminMenuStore.collapseSidebar();
        },

        expandAdminMenu() {
            this.adminMenuStore.expandSidebar();
        },

        mountedComponent() {
            this.$device.onResize({
                listener: () => {
                    this.viewportWidth = this.$device.getViewportWidth();
                },
                component: this,
            });

            this.addScrollbarOffset();
        },

        getUser() {
            this.isUserLoading = true;

            this.userService.getUser().then((response) => {
                const userData = response.data;
                delete userData.password;

                Shopware.Store.get('session').setCurrentUser(userData);

                this.isUserLoading = false;
            });
        },

        onToggleSidebar() {
            if (this.isExpanded) {
                this.collapseAdminMenu();
            } else {
                this.expandAdminMenu();
            }

            this.toggleSidebar();
        },

        onToggleSidebarShortcut() {
            // "S" also ends navigation sequences like "GS" and "AS" — skip the
            // toggle when the keypress completes one of those.
            if (this.shortcutService?.isPendingCombinationKey?.('S')) {
                return;
            }

            if (this.isMobileViewport) {
                return;
            }

            this.onToggleSidebar();
        },

        /**
         * Marks the sidebar as mid-toggle (`is--toggling`), which suppresses the logo/expand-button
         * crossfade on hover and scopes the nested tree-line transitions to the toggle window.
         * Must outlast the longest animation it gates: the root width/padding transition
         * (--sw-admin-menu-duration, 0.5s).
         */
        startSidebarToggleWindow() {
            this.isTogglingSidebar = true;

            if (this.toggleSidebarTimeout) {
                clearTimeout(this.toggleSidebarTimeout);
            }

            this.toggleSidebarTimeout = setTimeout(() => {
                this.isTogglingSidebar = false;
                this.toggleSidebarTimeout = null;
            }, SIDEBAR_TOGGLE_ANIMATION_DURATION);
        },

        toggleSidebar() {
            if (!this.isExpanded) {
                this.adminMenuStore.clearExpandedMenuEntries();
                this.onFlyoutLeave();
            }

            this.isUserActionsActive = false;
            this.flyoutEntries = [];
        },

        async onLogoutUser() {
            await this.loginService.logoutSso();

            this.adminMenuStore.clearExpandedMenuEntries();
            Shopware.Store.get('session').removeCurrentUser();
            Shopware.Store.get('notification').clearGrowlNotificationsForCurrentUser();
            Shopware.Store.get('notification').clearNotificationsForCurrentUser();
        },

        addScrollbarOffset() {
            const scrollbarWidthPx = dom.getScrollbarWidth(this.$refs.swAdminMenuBody);

            this.scrollbarOffset = `-${scrollbarWidthPx}px`;
        },

        onMenuBranchToggle({ entry, open }) {
            if (!this.isExpanded || !entry || entry.level !== 1) {
                return;
            }

            if (!open) {
                this.adminMenuStore.collapseMenuEntry(entry);
                return;
            }

            this.collapseInactiveBranches(entry);
            this.adminMenuStore.expandMenuEntry(entry);
        },

        /**
         * Accordion behaviour with one exception: a branch owning the currently
         * active route stays open until it is closed manually or the active item
         * moves to another branch.
         */
        collapseInactiveBranches(exceptEntry = null) {
            const exceptKey = exceptEntry ? (exceptEntry.id ?? exceptEntry.path) : null;
            const activeNames = getActiveRouteNames(this.$route, this.$router);

            this.adminMenuStore.expandedEntries
                .filter((expanded) => {
                    const key = expanded.id ?? expanded.path;

                    if (key === exceptKey) {
                        return false;
                    }

                    const menuEntry = this.mainMenuEntries.find((entry) => (entry.id ?? entry.path) === key);

                    return !menuEntry || !isEntryOnActiveRoute(menuEntry, this.$route, activeNames);
                })
                .forEach((expanded) => this.adminMenuStore.collapseMenuEntry(expanded));
        },

        onMenuItemHover(entry, eventTarget) {
            if (this.isExpanded) {
                return;
            }

            this.cancelFlyoutClose();

            const target = eventTarget.closest('.sw-admin-menu__navigation-list-item');

            if (!target) {
                return;
            }

            const hasChildrenClass = target.classList.contains('navigation-list-item__has-children');
            const children = hasChildrenClass ? this.getChildren(entry) : [];

            if (!hasChildrenClass || children.length === 0) {
                this.onFlyoutLeave();
                return;
            }

            const entryKey = entry.id || entry.path;
            const active = this.activeEntry?.entry;
            const activeKey = active ? active.id || active.path : null;

            if (activeKey === entryKey && this.flyoutEntries.length > 0) {
                return;
            }

            this.flyoutReferenceElement = target.querySelector('.sw-admin-menu__navigation-link') ?? target;
            this.flyoutEntries = children;
            this.flyoutTitle = this.getEntryLabel(entry);
            this.deactivatePreviousMenuItem();
            target.classList.add('is--flyout-enabled');

            this.activeEntry = { entry, target };
        },

        onNavigationListMouseLeave(event) {
            if (event.relatedTarget?.closest('.sw-admin-menu__flyout-content')) {
                return;
            }

            this.scheduleFlyoutClose();
        },

        onFlyoutMouseLeave(event) {
            if (event.relatedTarget?.closest('.sw-admin-menu__navigation-list')) {
                return;
            }

            this.scheduleFlyoutClose();
        },

        scheduleFlyoutClose() {
            if (this.isExpanded || !this.flyoutEntries.length) {
                return;
            }

            this.cancelFlyoutClose();

            this.flyoutCloseTimeoutId = window.setTimeout(() => {
                this.startFlyoutCloseAnimation();
            }, 180);
        },

        startFlyoutCloseAnimation() {
            if (!this.flyoutEntries.length) {
                return;
            }

            this.isFlyoutClosing = true;

            this.flyoutCloseTimeoutId = window.setTimeout(() => {
                this.onFlyoutLeave();
            }, 200);
        },

        cancelFlyoutClose() {
            if (this.flyoutCloseTimeoutId) {
                clearTimeout(this.flyoutCloseTimeoutId);
                this.flyoutCloseTimeoutId = null;
            }

            this.isFlyoutClosing = false;
        },

        getChildren(entry) {
            return entry.children.filter((child) => {
                if (!child.privilege) {
                    return true;
                }

                return this.acl.can(child.privilege);
            });
        },

        /**
         * Whether the flyout currently shows the children of the given entry.
         * Drives the aria-expanded state of the collapsed parent items.
         */
        isFlyoutEntryActive(entry) {
            if (this.isExpanded || this.flyoutEntries.length === 0) {
                return false;
            }

            const active = this.activeEntry?.entry;

            return !!active && (active.id || active.path) === (entry.id || entry.path);
        },

        /**
         * Moves keyboard focus into the flyout. The flyout is teleported to the
         * body and therefore unreachable via the natural tab order — a focus trap
         * keeps Tab cycling inside it; Escape deactivates the trap, closes the
         * flyout and returns focus to the menu entry it was opened from.
         */
        onFlyoutFocusRequest() {
            this.$nextTick(() => {
                const flyoutElement = this.$refs.swAdminMenuFlyout;

                if (!flyoutElement || this.flyoutEntries.length === 0) {
                    return;
                }

                this.flyoutFocusTrap = createFocusTrap(flyoutElement, {
                    escapeDeactivates: true,
                    clickOutsideDeactivates: true,
                    returnFocusOnDeactivate: true,
                    delayInitialFocus: false,
                    fallbackFocus: flyoutElement,
                    onDeactivate: () => {
                        this.flyoutFocusTrap = null;
                        this.onFlyoutLeave();
                    },
                });

                this.flyoutFocusTrap.activate();
            });
        },

        deactivateFlyoutFocusTrap(returnFocus = true) {
            if (!this.flyoutFocusTrap) {
                return;
            }

            const trap = this.flyoutFocusTrap;
            this.flyoutFocusTrap = null;

            // Override the configured onDeactivate: it closes the flyout via
            // onFlyoutLeave, which is the caller of this method.
            trap.deactivate({ returnFocus, onDeactivate: () => {} });
        },

        /**
         * All focusable navigation links inside the given container. Links of
         * closed collapsible branches stay in the DOM (hidden="until-found")
         * and are skipped — they cannot receive focus.
         */
        getNavigationLinks(container) {
            return Array.from(container.querySelectorAll('.sw-admin-menu__navigation-link')).filter(
                (link) => !link.closest('[hidden]'),
            );
        },

        /**
         * Optional arrow key support (APG disclosure navigation pattern):
         * ArrowDown/ArrowUp move through the given links, Home/End jump to the
         * first/last one.
         */
        moveListFocus(links, event) {
            if (links.length === 0) {
                return;
            }

            const currentIndex = links.indexOf(document.activeElement);
            let nextIndex = null;

            switch (event.key) {
                case 'ArrowDown':
                    nextIndex = currentIndex < 0 ? 0 : (currentIndex + 1) % links.length;
                    break;
                case 'ArrowUp':
                    nextIndex = currentIndex < 0 ? links.length - 1 : (currentIndex - 1 + links.length) % links.length;
                    break;
                case 'Home':
                    nextIndex = 0;
                    break;
                case 'End':
                    nextIndex = links.length - 1;
                    break;
                default:
                    return;
            }

            event.preventDefault();
            links[nextIndex]?.focus();
        },

        onNavigationKeydown(event) {
            const menuBody = this.$refs.swAdminMenuBody;

            if (!menuBody) {
                return;
            }

            this.moveListFocus(this.getNavigationLinks(menuBody), event);
        },

        onFlyoutKeydown(event) {
            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                // Mirror of ArrowRight on the menu entry: the trap returns the
                // focus to the entry the flyout was opened from.
                this.deactivateFlyoutFocusTrap(true);
                this.onFlyoutLeave();

                return;
            }

            const flyoutElement = this.$refs.swAdminMenuFlyout;

            if (!flyoutElement) {
                return;
            }

            this.moveListFocus(this.getNavigationLinks(flyoutElement), event);
        },

        onFlyoutLeave() {
            this.deactivateFlyoutFocusTrap();
            this.cancelFlyoutClose();
            this.deactivatePreviousMenuItem();
            this.flyoutReferenceElement = null;
            this.flyoutEntries = [];
            this.flyoutTitle = '';
        },

        deactivatePreviousMenuItem() {
            if (this.activeEntry && this.activeEntry.target) {
                this.activeEntry.target.classList.remove('is--flyout-enabled');
            }
            this.activeEntry = null;
        },

        getEntryLabel(entry) {
            if (entry.label instanceof Object) {
                return entry.label.translated ? entry.label.label : this.$t(entry.label.label);
            }

            return this.$t(entry.label);
        },

        expandAncestorBranchesForCurrentRoute() {
            if (!this.isExpanded) {
                return;
            }

            // Open the top-level branch that owns the current route. Derived from the resolved
            // route chain (+ parentPath bridge), so it works for any routing — including path-less
            // parents (e.g. "Extensions") and detail/create sub-pages.
            const activeNames = getActiveRouteNames(this.$route, this.$router);
            const owner = this.mainMenuEntries.find(
                (entry) => (entry.children?.length ?? 0) > 0 && isEntryOnActiveRoute(entry, this.$route, activeNames),
            );
            const ownerKey = owner ? (owner.id ?? owner.path) : null;

            // Same owning branch as before (e.g. tab navigation inside a module) —
            // keep the current expansion state so a manual close is not overridden.
            if (ownerKey === this.activeBranchKey) {
                return;
            }

            // The active item moved to another branch: branches only stay open while
            // they own the active item, so collapse the previous ones.
            this.collapseInactiveBranches(owner);
            this.activeBranchKey = ownerKey;

            if (owner && !this.isNavigationEntryExpanded(owner)) {
                this.adminMenuStore.expandMenuEntry(owner);
            }
        },

        isNavigationEntryExpanded(entry) {
            if (!entry) {
                return false;
            }

            const key = entry.id ?? entry.path;
            return this.adminMenuStore.expandedEntries.some((expanded) => (expanded.id ?? expanded.path) === key);
        },
    },
};
