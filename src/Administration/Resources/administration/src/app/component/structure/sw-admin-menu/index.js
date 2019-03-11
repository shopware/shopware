import { State, Mixin } from 'src/core/shopware';
import dom from 'src/core/service/utils/dom.utils';
import template from './sw-admin-menu.html.twig';
import './sw-admin-menu.scss';

/**
 * @private
 */
export default {
    name: 'sw-admin-menu',
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    inject: ['menuService', 'loginService', 'userService'],

    data() {
        return {
            isExpanded: true,
            isOffCanvasShown: false,
            isUserActionsActive: false,
            flyoutEntries: [],
            lastFlyoutEntries: [],
            flyoutStyle: {},
            flyoutColor: '',
            flyoutLabel: '',
            subMenuOpen: false,
            scrollbarOffset: '',
            userProfile: {},
            user: {}
        };
    },

    computed: {
        userStore() {
            return State.getStore('user');
        },

        localeStore() {
            return State.getStore('adminLocale');
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
            return this.user.name;
        },

        avatarUrl() {
            if (this.user.avatarMedia) {
                return this.user.avatarMedia.url;
            }

            return null;
        },

        firstName() {
            if (this.user.name) {
                return this.user.name.split(' ')[0];
            }
            return '';
        },

        lastName() {
            if (this.user.name) {
                return this.user.name.split(' ')[1];
            }
            return '';
        }
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        const that = this;

        this.$device.onResize({
            listener() {
                that.collapseMenuOnSmallViewports();
            },
            component: this
        });

        this.addScrollbarOffset();
    },

    methods: {
        createdComponent() {
            this.collapseMenuOnSmallViewports();

            this.userService.getUser().then((response) => {
                this.userProfile = response.data;
                this.user = this.userStore.getById(this.userProfile.id);
            }).catch((error) => {
                const status = error.response.status;
                if (status !== 401) {
                    this.createNotificationError({
                        title: this._name,
                        message: error.message
                    });
                    return;
                }
                this.loginService.logout();
                this.$router.replace({
                    name: 'sw.login.index'
                });
            });

            this.$root.$on('toggleOffCanvas', (state) => {
                this.isOffCanvasShown = state;
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
                this.isExpanded = false;
            }

            if (this.$device.getViewportWidth() <= 500) {
                this.isExpanded = true;
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

        onChangeLanguage() {
            const lastLocale = this.$root.$i18n.locale;
            const newLocale = (lastLocale === 'de-DE' ? 'en-GB' : 'de-DE');

            this.$root.$i18n.locale = newLocale;
            this.localeStore.setLocale(newLocale);
        },

        onToggleSidebar() {
            this.isExpanded = !this.isExpanded;

            if (!this.isExpanded) {
                this.closeFlyout();
            }

            this.flyoutEntries = [];
        },

        onToggleUserActions() {
            this.isUserActionsActive = !this.isUserActionsActive;
        },

        openUserActions() {
            if (this.isExpanded) {
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
            this.$router.push({
                name: 'sw.login.index'
            });
        },

        addScrollbarOffset() {
            const offset = dom.getScrollbarWidth(this.$refs.swAdminMenuBody);

            this.scrollbarOffset = `-${offset}px`;
        }
    }
};
