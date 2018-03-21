import { Component } from 'src/core/shopware';
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
            subMenuOpen: false
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
        }
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
                top: `${currentTarget.offsetTop}px`,
                'border-color': entry.color
            };

            return true;
        },

        closeFlyout() {
            this.flyoutEntries = [];
        },

        onToggleSidebar() {
            this.isExpanded = !this.isExpanded;
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
        }
    }
});
