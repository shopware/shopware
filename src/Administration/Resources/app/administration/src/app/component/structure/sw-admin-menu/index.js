import template from './sw-admin-menu.html.twig';
import './sw-admin-menu.scss';

// @deprecated tag:v6.4.0.0 for StateDeprecated
const { Component, StateDeprecated, Mixin } = Shopware;
const { dom } = Shopware.Utils;

/**
 * @private
 */
Component.register('sw-admin-menu', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        // @deprecated tag:v6.4.0.0
        Mixin.getByName('salutation')
    ],

    inject: [
        'menuService',
        'loginService',
        'userService',
        'appModulesService',
        'feature'
    ],

    data() {
        return {
            isOffCanvasShown: false,
            isUserActionsActive: false,
            flyoutEntries: [],
            lastFlyoutEntries: [],
            flyoutStyle: {},
            flyoutColor: '',
            flyoutLabel: '',
            subMenuOpen: false,
            scrollbarOffset: '',
            isUserLoading: true
        };
    },

    computed: {
        currentUser() {
            return Shopware.State.get('session').currentUser;
        },

        isExpanded() {
            return Shopware.State.get('adminMenu').isExpanded;
        },

        // @deprecated tag:v6.4.0.0
        userStore() {
            return StateDeprecated.getStore('user');
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

        appEntries() {
            return Shopware.State.getters['shopwareApps/navigation'];
        },

        mainMenuEntries() {
            const mainMenu = this.menuService.getMainMenu();

            // save menu entry for reactivity purposes
            const myAppsEntry = mainMenu.find((entry) => entry.id === 'sw-my-apps');

            if (myAppsEntry && this.appEntries.length > 0) {
                myAppsEntry.children = [...myAppsEntry.children, ...this.appEntries];
            }

            return mainMenu;
        },

        sidebarCollapseIcon() {
            return this.isExpanded ? 'default-arrow-circle-left' : 'default-arrow-circle-right';
        },

        userActionsToggleIcon() {
            return this.isUserActionsActive ? 'small-arrow-medium-down' : 'small-arrow-medium-up';
        },

        scrollbarOffsetStyle() {
            return {
                right: this.scrollbarOffset,
                'margin-left': this.scrollbarOffset
            };
        },

        adminMenuClasses() {
            return {
                'is--expanded': this.isExpanded,
                'is--collapsed': !this.isExpanded,
                'is--off-canvas-shown': this.isOffCanvasShown
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
        }
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
        document.addEventListener('mouseleave', this.closeFlyout);
    },

    beforeDestroy() {
        document.removeEventListener('mouseleave', this.closeFlyout);
    },

    methods: {
        createdComponent() {
            this.loginService.notifyOnLoginListener();

            this.collapseMenuOnSmallViewports();
            this.getUser();
            this.$root.$on('toggle-offcanvas', (state) => {
                this.isOffCanvasShown = state;
            });

            this.refreshApps();
        },

        refreshApps() {
            return this.appModulesService.fetchAppModules().then((modules) => {
                return Shopware.State.dispatch('shopwareApps/setAppModules', modules);
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
                component: this
            });

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

        openSubMenu(entry, currentTarget) {
            this.subMenuOpen = !this.subMenuOpen;

            if (this.$device.getViewportWidth() <= 500) {
                this.isOffCanvasShown = false;
            }

            if (this.isExpanded) {
                this.flyoutEntries = [];
            }

            this.changeActiveItem(currentTarget.querySelector('.sw-admin-menu__navigation-link'));
        },

        collapseMenuOnSmallViewports() {
            if (this.$device.getViewportWidth() <= 1200 && this.$device.getViewportWidth() >= 500) {
                this.collapseAdminMenu();
            }

            if (this.$device.getViewportWidth() <= 500) {
                this.expandAdminMenu();
            }
        },

        changeActiveItem(target) {
            const mainMenuElement = target.parentNode.parentNode;
            const activeClass = 'router-link-active';
            const listElements = mainMenuElement.querySelectorAll('.sw-admin-menu__navigation-link');

            listElements.forEach((listItem) => {
                listItem.classList.remove(activeClass);
            });

            target.classList.add(activeClass);
        },

        isActiveItem(menuItem) {
            return this.isExpanded && menuItem.classList.contains('router-link-active');
        },

        openFlyout(entry, currentTarget, parentEntries) {
            if (!currentTarget) {
                this.flyoutEntries = this.lastFlyoutEntries;
                return false;
            }

            if (parentEntries) {
                this.flyoutEntries = parentEntries;
                return true;
            }

            this.flyoutEntries = [];

            const menuItem = currentTarget.querySelector('.sw-admin-menu__navigation-link');

            if (this.isActiveItem(menuItem)) {
                return false;
            }

            if (this.$device.getViewportWidth() >= 500) {
                this.flyoutEntries = entry.children;
            }

            this.flyoutLabel = entry.label;

            this.flyoutStyle = {
                top: `${currentTarget.getBoundingClientRect().top}px`
            };
            this.flyoutColor = entry.color;

            return true;
        },
        closeFlyout(event) {
            if (event.toElement && event.toElement.closest('.sw-admin-menu__navigation-list-item')) {
                if (event.toElement.closest('.sw-admin-menu__navigation-list-item')
                    .classList.contains(this.flyoutEntries[0].parent)) {
                    return;
                }
            }
            this.lastFlyoutEntries = this.flyoutEntries;
            this.flyoutEntries = [];
        },

        onToggleSidebar() {
            if (this.isExpanded) {
                this.collapseAdminMenu();
            } else {
                this.expandAdminMenu();
            }

            if (!this.isExpanded) {
                this.closeFlyout();
            }

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
            Shopware.State.commit('removeCurrentUser');
            Shopware.State.commit('notification/setNotifications', {});
            Shopware.State.commit('notification/clearGrowlNotificationsForCurrentUser');
            Shopware.State.commit('notification/clearNotificationsForCurrentUser');
            this.$router.push({
                name: 'sw.login.index'
            });
        },

        openKeyboardShortcutOverview() {
            this.$refs.swShortcutOverview.onOpenShortcutOverviewModal();
        },

        addScrollbarOffset() {
            const offset = dom.getScrollbarWidth(this.$refs.swAdminMenuBody);

            this.scrollbarOffset = `-${offset}px`;
        },

        getMenuItemClass(entry) {
            const suffix = entry.id ? entry.id : entry.parent;
            return `sw-admin-menu__flyout-item--${suffix}`;
        }
    }
});
