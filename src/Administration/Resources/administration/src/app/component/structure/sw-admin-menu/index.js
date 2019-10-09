import template from './sw-admin-menu.html.twig';
import './sw-admin-menu.scss';

const { Component, State, Mixin } = Shopware;
const { dom } = Shopware.Utils;

/**
 * @private
 */
Component.register('sw-admin-menu', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('salutation')
    ],

    inject: ['menuService', 'loginService', 'userService'],

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
            isUserLoading: true,
            userProfile: {},
            user: {}
        };
    },

    computed: {
        isExpanded() {
            return this.$store.state.adminMenu.isExpanded;
        },

        userStore() {
            return State.getStore('user');
        },

        currentLocale() {
            return this.$store.state.adminLocale.currentLocale;
        },

        mainMenuEntries() {
            return this.menuService.getMainMenu();
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
            return this.salutation(this.user);
        },

        avatarUrl() {
            if (this.user.avatarMedia) {
                return this.user.avatarMedia.url;
            }

            return null;
        },

        firstName() {
            return this.user.firstName;
        },

        lastName() {
            return this.user.lastName;
        }
    },

    created() {
        this.createdComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        createdComponent() {
            this.collapseMenuOnSmallViewports();
            this.getUser();
            this.$root.$on('toggle-offcanvas', (state) => {
                this.isOffCanvasShown = state;
            });
        },

        collapseAdminMenu() {
            this.$store.commit('adminMenu/collapseSidebar');
        },

        expandAdminMenu() {
            this.$store.commit('adminMenu/expandSidebar');
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
            const currentUser = this.$store.state.adminUser.currentUser;

            this.isUserLoading = true;
            if (!currentUser) {
                this.userService.getUser().then((response) => {
                    this.userProfile = response.data;
                    return this.userStore.getByIdAsync(this.userProfile.id);
                }).then((user) => {
                    this.user = user;
                    this.$store.commit('adminUser/setCurrentProfile', user);
                    this.isUserLoading = false;
                });
                return true;
            }
            this.userProfile = currentUser;
            this.userStore.getByIdAsync(this.userProfile.id).then((user) => {
                this.user = user;
                this.$store.commit('adminUser/setCurrentProfile', user);
                this.isUserLoading = false;
            });

            return true;
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

        closeFlyout() {
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
            this.$store.commit('adminUser/removeCurrentUser');
            this.$store.commit('adminUser/removeCurrentProfile');
            this.$store.commit('notification/setNotifications', {});
            this.$store.commit('notification/clearGrowlNotificationsForCurrentUser');
            this.$store.commit('notification/clearNotificationsForCurrentUser');
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
