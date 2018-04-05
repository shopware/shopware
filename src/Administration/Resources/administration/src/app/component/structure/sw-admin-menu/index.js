import { Component } from 'src/core/shopware';
import dom from 'src/core/service/utils/dom.utils';
import template from './sw-admin-menu.html.twig';
import './sw-admin-menu.less';

Component.register('sw-admin-menu', {
    template,

    inject: ['menuService', 'loginService'],

    data() {
        return {
            isExpanded: true,
            isUserActionsActive: false,
            flyoutEntries: [],
            flyoutStyle: {},
            flyoutLabel: '',
            subMenuOpen: false,
            scrollbarOffset: ''
        };
    },

    computed: {
        mainMenuEntries() {
            return this.menuService.getMainMenu();
        },

        sidebarCollapseIcon() {
            return this.isExpanded ? 'default-arrow-circle-left' : 'default-arrow-circle-right';
        },

        userActionsToggleIcon() {
            return this.isUserActionsActive ? 'small-arrow-medium-up' : 'small-arrow-medium-down';
        },

        scrollbarOffsetStyle() {
            return {
                right: this.scrollbarOffset,
                'margin-left': this.scrollbarOffset
            };
        }
    },

    mounted() {
        this.addScrollbarOffset();
    },

    methods: {
        openSubMenu() {
            this.subMenuOpen = !this.subMenuOpen;
        },

        openFlyout(entry, currentTarget) {
            if (!currentTarget) {
                return false;
            }

            this.flyoutEntries = entry.children;
            this.flyoutLabel = entry.label;

            this.flyoutStyle = {
                top: `${currentTarget.getBoundingClientRect().top}px`,
                'border-color': entry.color
            };

            return true;
        },

        closeFlyout() {
            this.flyoutEntries = [];
        },

        onToggleSidebar() {
            this.isExpanded = !this.isExpanded;

            if (!this.isExpanded) {
                this.closeFlyout();
            }
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
            this.loginService.clearBearerAuthentication();
            this.$router.push({
                name: 'sw.login.index'
            });
        },

        addScrollbarOffset() {
            const offset = dom.getScrollbarWidth(this.$refs.swAdminMenuBody);

            this.scrollbarOffset = `-${offset}px`;
        }
    }
});
