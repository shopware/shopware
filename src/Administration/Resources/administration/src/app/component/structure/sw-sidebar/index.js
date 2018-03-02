import { Component } from 'src/core/shopware';
import './sw-sidebar.less';
import template from './sw-sidebar.html.twig';

Component.register('sw-sidebar', {
    inject: ['menuService', 'loginService'],
    template,

    data() {
        return {
            isCollapsed: false,
            isAccountMenuActive: false,
            flyoutEntries: [],
            flyoutStyle: {},
            subMenuOpen: false
        };
    },

    computed: {
        mainMenuEntries() {
            return this.menuService.getMainMenu();
        },

        sidebarCollapseIcon() {
            return this.isCollapsed ? 'default-arrow-circle-left' : 'default-arrow-circle-right';
        },

        accountMenuIcon() {
            return this.isAccountMenuActive ? 'small-arrow-medium-up' : 'small-arrow-medium-down';
        }
    },

    methods: {
        getShopData() {
            this.shopService.getList().then((response) => {
                return response.data;
            });
        },

        getMenuItemName(menuItemName) {
            return menuItemName.replace('.', '-');
        },

        openSubMenu(entry, currentTarget) {
            console.log(currentTarget);
            this.subMenuOpen = !this.subMenuOpen;
        },

        openFlyout(entry, currentTarget) {
            if (!currentTarget) {
                return false;
            }

            this.flyoutEntries = entry.children;

            this.flyoutStyle = {
                top: `${currentTarget.offsetTop}px`,
                'border-color': entry.color
            };

            return true;
        },

        onToggleSidebar() {
            this.isCollapsed = !this.isCollapsed;
        },

        onToggleAccountMenu() {
            this.isAccountMenuActive = !this.isAccountMenuActive;
        },

        onLogoutUser() {
            this.loginService.clearBearerAuthentication();
            this.$router.push({
                name: 'sw.login.index'
            });
        }
    }
});
