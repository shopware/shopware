import { Component } from 'src/core/shopware';
import './sw-sidebar.less';
import template from './sw-sidebar.html.twig';

Component.register('sw-sidebar', {
    inject: ['menuService', 'loginService'],
    template,

    computed: {
        mainMenuEntries() {
            return this.menuService.getMainMenu();
        }
    },

    methods: {
        onLogoutUser() {
            this.loginService.clearBearerAuthentication();
            this.$router.push({
                name: 'sw.login.index'
            });
        }
    }
});
