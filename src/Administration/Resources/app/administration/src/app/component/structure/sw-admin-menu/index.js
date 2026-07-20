import template from './sw-admin-menu.html.twig';
import { getActiveRouteNames, isEntryOnActiveRoute } from '../sw-admin-menu-item/menu-item-active.helper';
import './sw-admin-menu.scss';

const { Mixin } = Shopware;
const { dom } = Shopware.Utils;

/**
 * @sw-package framework
 *
 * @private
 */
export default {
    template,

    inject: [
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
            return this.viewportWidth !== null && this.viewportWidth <= 500;
        },

        userTitle() {
            if (this.currentUser && this.currentUser.admin) {
                return this.$tc('global.sw-admin-menu.administrator');
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

        currentExpandedMenuEntries() {
            return this.adminMenuStore.expandedEntries;
        },

        adminModuleNavigation() {
            const adminModuleNavigationEntries = this.adminMenuStore.adminModuleNavigation;

            // Throw an console error if navigation entry is on level 4 or higher. Also remove the navigation entry from menu
            return adminModuleNavigationEntries.filter((entry) => {
                const levelOneParent = adminModuleNavigationEntries.find((e) => entry.parent && e.id === entry.parent);
                // eslint-disable-next-line max-len
                const levelTwoParent = adminModuleNavigationEntries.find(
                    (e) => levelOneParent?.parent && e.id === levelOneParent?.parent,
                );
                // eslint-disable-next-line max-len
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

        sidebarCollapseIcon() {
            return 'solid-panel-right';
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
            this.suppressLogoHoverDuringToggle();
        },
        '$route.fullPath': {
            handler() {
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

        this.beforeUnmountedComponent();
    },

    methods: {
        createdComponent() {
            this.loginService.notifyOnLoginListener();

            this.viewportWidth = this.$device.getViewportWidth();
            this.getUser();
            this.loadShopName();

            Shopware.Utils.EventBus.on('sw-admin-menu/toggle-offcanvas', this.onToggleCanvas);

            this.initNavigation();
        },

        loadShopName() {
            this.systemConfigApiService.getValues('core.basicInformation').then((values) => {
                this.shopName = values['core.basicInformation.shopName'] ?? '';
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

        suppressLogoHoverDuringToggle() {
            this.isTogglingSidebar = true;

            if (this.toggleSidebarTimeout) {
                clearTimeout(this.toggleSidebarTimeout);
            }

            // 300ms matches the 0.3s panel width transition.
            this.toggleSidebarTimeout = setTimeout(() => {
                this.isTogglingSidebar = false;
                this.toggleSidebarTimeout = null;
            }, 300);
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

        onMenuItemClick(entry, eventTarget) {
            if (!this.isExpanded) {
                return;
            }

            if (eventTarget?.closest?.('.sw-admin-menu__sub-navigation-list')) {
                return;
            }

            if (this.flyoutEntries.length) {
                this.flyoutEntries = [];
                this.flyoutTitle = '';
            }
        },

        onMenuBranchToggle({ entry, open }) {
            if (!this.isExpanded || !entry || entry.level !== 1) {
                return;
            }

            if (!open) {
                this.adminMenuStore.collapseMenuEntry(entry);
                return;
            }

            this.adminMenuStore.clearExpandedMenuEntries();
            this.adminMenuStore.expandMenuEntry(entry);
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

            this.activeEntry = { entry, target, parentEntries: [] };
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

        getSingleChildTooltipConfig(entry) {
            const children = this.getChildren(entry);
            const shouldShowTooltip = !this.isExpanded && children.length === 0;

            return {
                message: shouldShowTooltip ? this.getEntryLabel(entry) : '',
                disabled: !shouldShowTooltip,
            };
        },

        onFlyoutLeave() {
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
                return entry.label.translated ? entry.label.label : this.$tc(entry.label.label);
            }

            return this.$tc(entry.label);
        },

        expandAncestorBranchesForCurrentRoute() {
            if (!this.isExpanded) {
                return;
            }

            // Open the top-level branch that owns the current route. Derived from the resolved
            // route chain (+ parentPath bridge), so it works for any routing — including path-less
            // parents (e.g. "Extensions") and detail/create sub-pages.
            const activeNames = getActiveRouteNames(this.$route, this.$router);

            this.mainMenuEntries.forEach((entry) => {
                if (
                    (entry.children?.length ?? 0) > 0 &&
                    isEntryOnActiveRoute(entry, this.$route, activeNames) &&
                    !this.isNavigationEntryExpanded(entry)
                ) {
                    this.adminMenuStore.expandMenuEntry(entry);
                }
            });
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
