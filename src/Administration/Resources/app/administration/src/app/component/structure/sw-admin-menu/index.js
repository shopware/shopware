import { createFocusTrap } from 'focus-trap';
import template from './sw-admin-menu.html.twig';
import { getActiveRouteNames, isEntryOnActiveRoute } from '../sw-admin-menu-item/menu-item-active.helper';
import './sw-admin-menu.scss';

const { Mixin } = Shopware;
const { dom } = Shopware.Utils;

const SIDEBAR_TOGGLE_ANIMATION_DURATION = 500;

const VIEWPORT_RESIZE_SETTLE_DURATION = 200;

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
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    shortcuts: {
        S: {
            active() {
                return !this.isMobileViewport;
            },
            method: 'onToggleSidebar',
        },
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
            isFlyoutPinned: false,
            scrollbarOffset: '',
            isUserLoading: true,
            flyoutReferenceElement: null,
            viewportWidth: null,
            shopName: '',
            isTogglingSidebar: false,
            toggleSidebarTimeout: null,
            isViewportResizing: false,
            viewportResizeTimeout: null,
            activeBranchKey: null,
        };
    },

    computed: {
        currentUser() {
            return Shopware.Store.get('session').currentUser;
        },

        isExpanded() {
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

            return '';
        },

        currentLocale() {
            return Shopware.Store.get('session').currentLocale;
        },

        adminModuleNavigation() {
            const adminModuleNavigationEntries = this.adminMenuStore.adminModuleNavigation;

            // Nesting beyond level 3 is unsupported: report it and drop the entry.
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
                'is--viewport-resizing': this.isViewportResizing,
            };
        },

        userName() {
            if (!this.currentUser) {
                return '';
            }

            return `${this.currentUser.firstName} ${this.currentUser.lastName}`;
        },

        userActionsAriaLabel() {
            // The collapsed sidebar hides the visible user name, leaving the avatar button unnamed
            return [
                this.userName,
                this.userTitle,
            ]
                .filter(Boolean)
                .join(', ');
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
        isOffCanvasShown(isShown) {
            if (isShown) {
                this.activateOffCanvasFocusTrap();
            } else {
                this.deactivateOffCanvasFocusTrap();
            }
        },
        isMobileViewport(isMobile) {
            if (!isMobile && this.isOffCanvasShown) {
                this.closeOffCanvas();
            }

            // The teleported user menu would float detached over the hidden off-canvas rail otherwise
            if (isMobile) {
                this.isUserActionsActive = false;
            }
        },
        // Query-insensitive on purpose: listing pagination/sorting must not re-expand a collapsed branch
        '$route.path': {
            handler() {
                this.closeNavigationOverlays();

                // The teleported user menu would survive the page change otherwise
                this.isUserActionsActive = false;

                // Ensure the branch owning the new page is open, once the route change has rendered
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
            this.flyoutFocusTrap = null;
            this.offCanvasFocusTrap = null;
            this.menuDropdownObserver = null;
            this.openMenuDropdownTrigger = null;

            this.loginService.notifyOnLoginListener();

            this.viewportWidth = this.$device.getViewportWidth();
            this.getUser();
            this.loadShopName();

            Shopware.Utils.EventBus.on('sw-admin-menu/toggle-offcanvas', this.onToggleCanvas);

            window.addEventListener('resize', this.onViewportResize);

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
            this.deactivateOffCanvasFocusTrap();
            Shopware.Utils.EventBus.off('sw-admin-menu/toggle-offcanvas', this.onToggleCanvas);
            window.removeEventListener('resize', this.onViewportResize);

            if (this.toggleSidebarTimeout) {
                clearTimeout(this.toggleSidebarTimeout);
            }

            if (this.viewportResizeTimeout) {
                clearTimeout(this.viewportResizeTimeout);
            }
        },

        onViewportResize() {
            this.viewportWidth = this.$device.getViewportWidth();
            this.isViewportResizing = true;

            if (this.viewportResizeTimeout) {
                clearTimeout(this.viewportResizeTimeout);
            }

            this.viewportResizeTimeout = setTimeout(() => {
                this.isViewportResizing = false;
            }, VIEWPORT_RESIZE_SETTLE_DURATION);
        },

        onToggleCanvas(state) {
            this.isOffCanvasShown = state;
        },

        closeOffCanvas() {
            this.isOffCanvasShown = false;
            Shopware.Utils.EventBus.emit('sw-admin-menu/toggle-offcanvas', false);
        },

        closeNavigationOverlays() {
            // Ensure an open flyout closes once the page changes
            if (!this.isExpanded && this.flyoutEntries.length && !this.isFlyoutPinned) {
                // Ensure the keyboard focus stays on the new page
                this.deactivateFlyoutFocusTrap(false);
                this.onFlyoutLeave();
            }

            // Make sure the mobile off-canvas panel closes so the new page is not left hidden behind it
            if (this.isMobileViewport && this.isOffCanvasShown) {
                this.closeOffCanvas();
            }
        },

        onNavigationLinkClicked() {
            // Tapping the current route's entry aborts as redundant navigation, so no route watcher fires
            this.closeNavigationOverlays();
        },

        dismissOffCanvas() {
            // Explicit dismissal restores focus to the opener
            if (this.offCanvasFocusTrap) {
                this.offCanvasFocusTrap.deactivate();
                return;
            }

            this.closeOffCanvas();
        },

        activateOffCanvasFocusTrap() {
            this.$nextTick(() => {
                const panelElement = this.$refs.swAdminMenu;

                if (!panelElement || !this.isOffCanvasShown || this.offCanvasFocusTrap) {
                    return;
                }

                this.offCanvasFocusTrap = createFocusTrap(panelElement, {
                    escapeDeactivates: true,
                    clickOutsideDeactivates: false,
                    allowOutsideClick: true,
                    returnFocusOnDeactivate: true,
                    delayInitialFocus: false,
                    fallbackFocus: panelElement,
                    onDeactivate: () => {
                        this.stopMenuDropdownObserver();
                        this.offCanvasFocusTrap = null;
                        Shopware.Utils.EventBus.emit('sw-admin-menu/toggle-offcanvas', false);
                    },
                });

                this.offCanvasFocusTrap.activate();
                this.startMenuDropdownObserver(panelElement);
            });
        },

        startMenuDropdownObserver(panelElement) {
            this.menuDropdownObserver = new MutationObserver(this.syncMenuDropdownFocusOwner);

            this.menuDropdownObserver.observe(panelElement, {
                subtree: true,
                childList: true,
                attributes: true,
                attributeFilter: ['data-state'],
            });
        },

        stopMenuDropdownObserver() {
            this.menuDropdownObserver?.disconnect();
            this.menuDropdownObserver = null;
            this.openMenuDropdownTrigger = null;
        },

        syncMenuDropdownFocusOwner() {
            if (!this.offCanvasFocusTrap) {
                return;
            }

            // aria-haspopup narrows this to dropdown triggers: open navigation collapsibles share the same data-state
            const openTrigger = this.$refs.swAdminMenu?.querySelector('[aria-haspopup="menu"][data-state="open"]');

            if (openTrigger && !this.openMenuDropdownTrigger) {
                this.openMenuDropdownTrigger = openTrigger;
                this.offCanvasFocusTrap.pause();

                return;
            }

            if (!openTrigger && this.openMenuDropdownTrigger) {
                const previousTrigger = this.openMenuDropdownTrigger;
                this.openMenuDropdownTrigger = null;

                if (previousTrigger.isConnected) {
                    previousTrigger.focus();
                }

                this.offCanvasFocusTrap.unpause();
            }
        },

        deactivateOffCanvasFocusTrap() {
            if (!this.offCanvasFocusTrap) {
                return;
            }

            const trap = this.offCanvasFocusTrap;
            this.offCanvasFocusTrap = null;

            trap.deactivate({ returnFocus: false });
        },

        initNavigation() {
            this.adminMenuStore.adminModuleNavigation = this.menuService.getNavigationFromAdminModules();

            this.refreshApps();
        },

        refreshApps() {
            return this.appModulesService.fetchAppModules().then((modules) => {
                Shopware.Store.get('shopwareApps').apps = modules;
                Shopware.Store.get('shopwareApps').appsLoaded = true;

                this.$nextTick(() => this.expandAncestorBranchesForCurrentRoute());
            });
        },

        collapseAdminMenu() {
            this.adminMenuStore.collapseSidebar();
        },

        expandAdminMenu() {
            this.adminMenuStore.expandSidebar();
        },

        mountedComponent() {
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

        startSidebarToggleWindow() {
            // Marks the sidebar as mid-toggle so CSS can suppress unwanted animations while it slides
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
            // Collapsing hides the expanded tree, so drop that state and close anything left floating
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
            // A negative offset pulls the scrollbar outside the menu so it does not eat into the visible width
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
            this.isFlyoutPinned = false;
            this.flyoutEntries = children;
            this.flyoutTitle = this.getEntryLabel(entry);

            this.activeEntry = { entry, target };
        },

        onNavigationListMouseLeave(event) {
            if (this.isSuppressedFlyoutFocusOut(event)) {
                return;
            }

            if (event.relatedTarget?.closest('.sw-admin-menu__flyout-content')) {
                return;
            }

            this.scheduleFlyoutClose();
        },

        onFlyoutMouseLeave(event) {
            if (this.isSuppressedFlyoutFocusOut(event)) {
                return;
            }

            if (event.relatedTarget?.closest('.sw-admin-menu__navigation-list')) {
                return;
            }

            this.scheduleFlyoutClose();
        },

        isSuppressedFlyoutFocusOut(event) {
            return event.type === 'focusout' && this.isFlyoutPinned;
        },

        onFlyoutNavigate({ disclosesChildren }) {
            this.isFlyoutPinned = disclosesChildren;
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

        isFlyoutEntryActive(entry) {
            if (this.isExpanded || this.flyoutEntries.length === 0) {
                return false;
            }

            const active = this.activeEntry?.entry;

            return !!active && (active.id || active.path) === (entry.id || entry.path);
        },

        onFlyoutFocusRequest() {
            this.$nextTick(() => {
                const flyoutElement = this.$refs.swAdminMenuFlyout;

                if (!flyoutElement || this.flyoutEntries.length === 0) {
                    return;
                }

                this.deactivateFlyoutFocusTrap(false);

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

            // Override the configured onDeactivate: it closes the flyout via onFlyoutLeave
            trap.deactivate({ returnFocus, onDeactivate: () => {} });
        },

        getNavigationLinks(container) {
            return Array.from(container.querySelectorAll('.sw-admin-menu__navigation-link')).filter(
                (link) => !link.closest('[hidden]'),
            );
        },

        moveListFocus(links, event) {
            // arrow key support, per the APG disclosure navigation pattern.

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
            this.isFlyoutPinned = false;
            this.activeEntry = null;
            this.flyoutReferenceElement = null;
            this.flyoutEntries = [];
            this.flyoutTitle = '';
        },

        getEntryLabel(entry) {
            if (entry.label instanceof Object) {
                return entry.label.translated ? entry.label.label : this.$t(entry.label.label);
            }

            return this.$t(entry.label);
        },

        expandAncestorBranchesForCurrentRoute() {
            // Only the expanded sidebar shows a tree to open — collapsed entries use the flyout instead
            if (!this.isExpanded) {
                return;
            }

            const activeNames = getActiveRouteNames(this.$route, this.$router);
            const activeEntries = this.mainMenuEntries.filter((entry) =>
                isEntryOnActiveRoute(entry, this.$route, activeNames),
            );

            // Pages the menu does not list at all own no branch — leave the tree as the user left it
            if (!activeEntries.length) {
                return;
            }

            const owner = activeEntries.find((entry) => (entry.children?.length ?? 0) > 0);
            const ownerKey = owner ? (owner.id ?? owner.path) : null;

            // The cached owner may have been collapsed manually
            if (ownerKey === this.activeBranchKey && (!owner || this.isNavigationEntryExpanded(owner))) {
                return;
            }

            // Branches only stay open while they own the active item, or while nothing in the menu does.
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
