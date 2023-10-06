import template from './sw-admin-menu.html.twig';
import './sw-admin-menu.scss';

const { Component, Mixin } = Shopware;
const { dom, types } = Shopware.Utils;

/**
 * @package admin
 *
 * @private
 */
Component.register('sw-admin-menu', {
    template,

    inject: [
        'menuService',
        'loginService',
        'userService',
        'appModulesService',
        'feature',
        'customEntityDefinitionService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        mouseLocationsTracked: {
            type: Number,
            required: false,
            default() {
                return 3;
            },
        },
        subMenuDelay: {
            type: Number,
            required: false,
            default() {
                return 150;
            },
        },
    },

    data() {
        return {
            subMenuTimer: null,
            mouseLocations: [],
            lastDelayLocation: null,
            activeEntry: null,
            isOffCanvasShown: false,
            isUserActionsActive: false,
            flyoutEntries: [],
            lastFlyoutEntries: [],
            flyoutStyle: {},
            flyoutColor: '',
            flyoutLabel: '',
            subMenuOpen: false,
            scrollbarOffset: '',
            isUserLoading: true,
        };
    },

    computed: {
        currentUser() {
            return Shopware.State.get('session').currentUser;
        },

        isExpanded() {
            return Shopware.State.get('adminMenu').isExpanded;
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
            return Shopware.State.get('session').currentLocale;
        },

        currentExpandedMenuEntries() {
            return Shopware.State.get('adminMenu').expandedEntries;
        },

        adminModuleNavigation() {
            const adminModuleNavigationEntries = Shopware.State.get('adminMenu').adminModuleNavigation;

            // Throw an console error if navigation entry is on level 4 or higher. Also remove the navigation entry from menu
            return adminModuleNavigationEntries.filter((entry) => {
                const levelOneParent = adminModuleNavigationEntries.find(e => entry.parent && e.id === entry.parent);
                // eslint-disable-next-line max-len
                const levelTwoParent = adminModuleNavigationEntries.find(e => levelOneParent?.parent && e.id === levelOneParent?.parent);
                // eslint-disable-next-line max-len
                const levelThreeParent = adminModuleNavigationEntries.find(e => levelTwoParent?.parent && e.id === levelTwoParent?.parent);

                if (levelThreeParent) {
                    Shopware.Utils.debug.error(new Error(
                        `The navigation entry "${entry.id}" is nested on level 4 or higher.\
The admin menu only supports up to three levels of nesting.`,
                    ));

                    return false;
                }

                return true;
            });
        },

        appModuleNavigation() {
            return Shopware.State.getters['adminMenu/appModuleNavigation'];
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
            return this.isExpanded ? 'regular-chevron-circle-left' : 'regular-chevron-circle-right';
        },

        userActionsToggleIcon() {
            return this.isUserActionsActive ? 'regular-chevron-down-xs' : 'regular-chevron-up-xs';
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
            return Shopware.State.get('menuItem').menuItems;
        },

        extensionModuleNavigation() {
            return this.extensionMenuItems.map((extensionMenuItem) => {
                return {
                    id: Shopware.Utils.createId(),
                    label: {
                        translated: true,
                        label: extensionMenuItem.label,
                    },
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
    },

    watch: {
        isExpanded() {
            this.toggleSidebar();
        },
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
        document.addEventListener('mouseleave', this.onFlyoutLeave);
    },

    beforeDestroy() {
        document.removeEventListener('mousemove', this.onMouseMoveDocument);
        document.removeEventListener('mouseleave', this.onFlyoutLeave);
    },

    methods: {
        createdComponent() {
            this.loginService.notifyOnLoginListener();

            this.collapseMenuOnSmallViewports();
            this.getUser();
            this.$root.$on('toggle-offcanvas', (state) => {
                this.isOffCanvasShown = state;
            });

            this.initNavigation();
        },

        initNavigation() {
            Shopware.State.commit('adminMenu/setAdminModuleNavigation', this.menuService.getNavigationFromAdminModules());

            this.refreshApps();
        },

        refreshApps() {
            return this.appModulesService.fetchAppModules().then((modules) => {
                return Shopware.State.commit('shopwareApps/setApps', modules);
            });
        },

        collapseAdminMenu() {
            Shopware.State.commit('adminMenu/collapseSidebar');
        },

        expandAdminMenu() {
            Shopware.State.commit('adminMenu/expandSidebar');
        },

        mountedComponent() {
            const that = this;

            this.$device.onResize({
                listener() {
                    that.collapseMenuOnSmallViewports();
                },
                component: this,
            });

            document.addEventListener('mousemove', this.onMouseMoveDocument.bind(this));

            this.addScrollbarOffset();
        },

        getUser() {
            this.isUserLoading = true;

            this.userService.getUser().then((response) => {
                const userData = response.data;
                delete userData.password;

                Shopware.State.commit('setCurrentUser', userData);

                this.isUserLoading = false;
            });
        },

        collapseMenuOnSmallViewports() {
            if (this.$device.getViewportWidth() <= 1200 && this.$device.getViewportWidth() >= 500) {
                this.collapseAdminMenu();
            }

            if (this.$device.getViewportWidth() <= 500) {
                this.expandAdminMenu();
            }
        },

        isActiveItem(menuItem) {
            return this.isExpanded && menuItem.classList.contains('router-link-active');
        },

        onToggleSidebar() {
            if (this.isExpanded) {
                this.collapseAdminMenu();
            } else {
                this.expandAdminMenu();
            }

            this.toggleSidebar();
        },

        toggleSidebar() {
            if (!this.isExpanded) {
                this.removeClassesFromElements(
                    Array.from(this.$el.querySelectorAll('.sw-admin-menu__navigation-list-item')),
                    ['is--entry-expanded'],
                );

                const currentActiveElement = this.$el.querySelector('a.router-link-active');
                const currentActiveParentElement = currentActiveElement?.parentElement;
                const parentIsFirstLevel = currentActiveParentElement?.classList?.contains('navigation-list-item__level-1');

                const ignoreElementsList = [currentActiveParentElement];

                if (currentActiveElement && !parentIsFirstLevel) {
                    const mainMenuListItem = currentActiveElement.closest(
                        '.navigation-list-item__level-1.navigation-list-item__has-children',
                    );
                    ignoreElementsList.push(mainMenuListItem.firstElementChild);
                }

                this.removeClassesFromElements(
                    Array.from(this.$el.querySelectorAll(
                        '.navigation-list-item__level-1.navigation-list-item__has-children > .router-link-active',
                    )),
                    ['router-link-active'],
                    ignoreElementsList,
                );
                this.onFlyoutLeave();
            }

            this.isUserActionsActive = false;
            this.flyoutEntries = [];
        },

        onToggleUserActions() {
            if (this.isUserLoading) {
                return false;
            }
            this.isUserActionsActive = !this.isUserActionsActive;
            return true;
        },

        openUserActions() {
            if (this.isExpanded || this.isUserLoading) {
                return;
            }

            this.isUserActionsActive = true;
        },

        closeUserActions() {
            if (this.isExpanded) {
                return;
            }

            this.isUserActionsActive = false;
        },

        onLogoutUser() {
            this.loginService.logout();
            Shopware.State.commit('adminMenu/clearExpandedMenuEntries');
            Shopware.State.commit('removeCurrentUser');
            Shopware.State.commit('notification/setNotifications', {});
            Shopware.State.commit('notification/clearGrowlNotificationsForCurrentUser');
            Shopware.State.commit('notification/clearNotificationsForCurrentUser');
            this.$router.push({
                name: 'sw.login.index',
            });
        },

        openKeyboardShortcutOverview() {
            this.$refs.swShortcutOverview.onOpenShortcutOverviewModal();
        },

        addScrollbarOffset() {
            const offset = dom.getScrollbarWidth(this.$refs.swAdminMenuBody);

            this.scrollbarOffset = `-${offset}px`;
        },

        onMouseMoveDocument(event) {
            this.mouseLocations.push({
                x: event.pageX,
                y: event.pageY,
            });

            // Mouse locations array exceeds the configured threshold
            if (this.mouseLocations.length > this.mouseLocationsTracked) {
                this.mouseLocations.shift();
            }
        },

        onMenuItemClick(entry, eventTarget) {
            const target = eventTarget.closest('.sw-admin-menu__navigation-list-item');
            const level = entry.level;

            // Clear previous delay of the menu
            if (this.subMenuTimer) {
                window.clearTimeout(this.subMenuTimer);
            }

            if (level > 1 || !target.classList.contains('navigation-list-item__has-children') || !this.isExpanded) {
                return;
            }

            const firstChild = target.firstChild;
            this.removeClassesFromElements(
                Array.from(this.$el.querySelectorAll(
                    '.sw-admin-menu__navigation-list-item',
                )),
                ['is--entry-expanded', 'is--flyout-expanded'],
                [target, firstChild],
            );

            const isEntryExpanded = target.classList.contains('is--entry-expanded');
            const isChildRouterActive = target.querySelector('a.router-link-active');
            if (!isChildRouterActive) {
                firstChild.classList.remove('router-link-active');
            } else {
                firstChild.classList.add('router-link-active');
            }

            if (isEntryExpanded) {
                Shopware.State.commit('adminMenu/collapseMenuEntry', entry);

                firstChild.classList.remove('router-link-active');
                firstChild.classList.remove('is--entry-expanded');
            } else {
                Shopware.State.commit('adminMenu/expandMenuEntry', entry);

                firstChild.classList.add('router-link-active');
                target.classList.add('is--entry-expanded');
            }

            target.classList.remove('is--flyout-expanded');

            // Clear flyout entries if clicked
            if (this.flyoutEntries.length) {
                this.flyoutEntries = [];
            }
        },

        onMenuLeave() {
            if (this.subMenuTimer) {
                window.clearTimeout(this.subMenuTimer);
            }

            this.deactivatePreviousMenuItem();
            this.flyoutEntries = [];
        },

        onMenuItemEnter(entry, event, parentEntries) {
            const target = event.target;

            // Clear previous delay of the menu
            if (this.subMenuTimer) {
                window.clearTimeout(this.subMenuTimer);
            }

            // Menu is expanded, so we don't have to activate the flyout
            if (target.classList.contains('is--entry-expanded')) {
                return;
            }

            // We don't have children, we don't need to do anything here.
            if (!target.classList.contains('navigation-list-item__has-children')) {
                this.deactivatePreviousMenuItem();
                this.flyoutEntries = [];
                return;
            }

            this.possiblyActivate(entry, target, parentEntries);
        },

        /* istanbul ignore next - is covered by E2E test */
        onSubMenuItemEnter(entry, event) {
            const target = event.target;
            const parent = target.closest('.is--entry-expanded');

            if (!parent) {
                return;
            }

            this.removeClassesFromElements(
                Array.from(parent.querySelectorAll('.sw-admin-menu__navigation-list-item')),
                ['is--flyout-enabled'],
                [target],
            );

            if (!this.getChildren(entry).length) {
                this.flyoutEntries = [];
                return;
            }

            target.classList.add('is--flyout-enabled');
            this.flyoutStyle = {
                top: `${target.getBoundingClientRect().top - document.getElementById('app').getBoundingClientRect().top}px`,
            };

            this.flyoutEntries = this.getChildren(entry);

            const parentEntry = this.mainMenuEntries.find((item) => {
                return item.id === entry.parent || item.path === entry.parent;
            });

            if (!parentEntry) {
                return;
            }
            this.flyoutColor = parentEntry.color;
        },

        getChildren(entry) {
            return entry.children.filter(child => {
                if (!child.privilege) {
                    return true;
                }

                return this.acl.can(child.privilege);
            });
        },

        isPositionInPolygon(x, y, polygon) {
            // eslint-disable-next-line inclusive-language/use-inclusive-words
            // Inspired by https://github.com/substack/point-in-polygon/blob/master/index.js
            let inside = false;

            // eslint-disable-next-line no-plusplus
            for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
                const xi = polygon[i][0];
                const yi = polygon[i][1];
                const xj = polygon[j][0];
                const yj = polygon[j][1];

                const intersect = ((yi > y) !== (yj > y)) &&
                    (x < (((xj - xi) * (y - yi)) / (yj - yi)) + xi);
                if (intersect) inside = !inside;
            }

            return inside;
        },

        possiblyActivate(entry, currentTarget, parentEntries) {
            const delay = this.getActivationDelay(currentTarget, entry);

            if (delay) {
                this.subMenuTimer = window.setTimeout(
                    this.possiblyActivate.bind(this, entry, currentTarget, parentEntries, true),
                    delay,
                );
                return;
            }

            this.activateMenuItem(entry, currentTarget, parentEntries);
        },

        activateMenuItem(entry, target, parentEntries) {
            if (this.getChildren(entry)) {
                this.flyoutEntries = this.getChildren(entry);
            }

            this.flyoutStyle = {
                top: `${target.getBoundingClientRect().top - document.getElementById('app').getBoundingClientRect().top}px`,
            };

            // Remove previous flyout enabled
            this.deactivatePreviousMenuItem();
            target.classList.add('is--flyout-enabled');

            if (this.subMenuTimer) {
                window.clearTimeout(this.subMenuTimer);
            }
            this.flyoutColor = entry.color;
            this.activeEntry = { entry, target, parentEntries };
        },

        deactivatePreviousMenuItem() {
            if (this.activeEntry && this.activeEntry.target) {
                this.activeEntry.target.classList.remove('is--flyout-enabled');
            }
            this.activeEntry = [];
        },

        getPolygonFromMenuItem(element, entry) {
            const outerWidth = (el) => {
                let width = el.offsetWidth;
                const style = el.currentStyle || getComputedStyle(el);

                width += (parseInt(style.marginLeft, 10) || 0);
                return width;
            };

            const outerHeight = (el) => {
                let height = el.offsetHeight;
                const style = el.currentStyle || getComputedStyle(el);

                height += (parseInt(style.marginTop, 10) || 0);
                return height;
            };

            const targetRect = element.getBoundingClientRect();
            const targetHeight = outerHeight(element);
            const targetWidth = outerWidth(element);
            const subMenuHeight = this.getChildren(entry).length * targetHeight;

            const topLeft = {
                x: targetRect.left,
                y: targetRect.top,
            };

            const bottomLeft = {
                x: topLeft.x,
                y: topLeft.y + targetHeight,
            };

            const topRight = {
                x: topLeft.x + (targetWidth * 2),
                y: topLeft.y,
            };

            const bottomRight = {
                x: topRight.x,
                y: topRight.y + subMenuHeight,
            };

            return [
                [topLeft.x, topLeft.y],
                [bottomLeft.x, bottomLeft.y],
                [bottomRight.x, bottomRight.y],
                [topRight.x, topRight.y],
            ];
        },

        getActivationDelay() {
            const currentMousePosition = this.mouseLocations[this.mouseLocations.length - 1];

            // No current mouse position, so we activate right away
            if (!currentMousePosition) {
                return 0;
            }

            // If there is no flyout already active, then activate immediately.
            if (!this.flyoutEntries.length) {
                return 0;
            }

            if (this.lastDelayLocation
                && currentMousePosition.x === this.lastDelayLocation.x
                && currentMousePosition.y === this.lastDelayLocation.y) {
                return 0;
            }

            // We have a previous active entry
            if (this.activeEntry !== null) {
                const previousPolygon = this.getPolygonFromMenuItem(this.activeEntry.target, this.activeEntry.entry);

                // We're inside the polygon
                if (this.isPositionInPolygon(currentMousePosition.x, currentMousePosition.y, previousPolygon)) {
                    this.lastDelayLocation = currentMousePosition;
                    return this.subMenuDelay;
                }
            }

            return 0;
        },

        onFlyoutEnter() {
            if (this.subMenuTimer) {
                window.clearTimeout(this.subMenuTimer);
            }
        },

        onFlyoutLeave() {
            this.deactivatePreviousMenuItem();
            this.activeEntry = null;
            this.flyoutEntries = [];
        },

        removeClassesFromElements(elements, classList, ignoreElementsList = []) {
            elements.forEach((element) => {
                if (ignoreElementsList.includes(element)) {
                    return;
                }
                element.classList.remove(classList);
            });
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
    },
});
