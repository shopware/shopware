import { Component, Mixin } from 'src/core/shopware';
import template from './sw-plugin-license-list.html.twig';
import './sw-plugin-license-list.scss';

Component.register('sw-plugin-license-list', {
    template,

    inject: ['storeService'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification')
    ],

    data() {
        return {
            licenses: [],
            isLoading: false,
            showLoginModal: false,
            isLoggedIn: false
        };
    },

    watch: {
        '$root.$i18n.locale'() {
            this.getList();
        }
    },

    methods: {
        downloadPlugin(pluginName, update = false) {
            this.storeService.downloadPlugin(pluginName).then(() => {
                if (update) {
                    this.createNotificationSuccess({
                        title: this.$tc('sw-plugin.updates.titleUpdateSuccess'),
                        message: this.$tc('sw-plugin.updates.messageUpdateSuccess')
                    });
                } else {
                    this.createNotificationSuccess({
                        title: this.$tc('sw-plugin.general.titleDownloadSuccess'),
                        message: this.$tc('sw-plugin.general.messageDownloadSuccess')
                    });
                }
                this.getList();
            });
        },

        getList() {
            this.loadLicenses();
        },

        loadLicenses() {
            this.isLoading = true;
            this.storeService.getLicenseList().then((response) => {
                this.licenses = response.items;
                this.total = 0;
                this.isLoading = false;
                this.isLoggedIn = true;
            })
                .catch((exception) => {
                    this.isLoading = false;
                    this.isLoggedIn = false;
                    if (exception.response && exception.response.data && exception.response.data.errors) {
                        const unauthorized = exception.response.data.errors.find((error) => {
                            return parseInt(error.code, 10) === 401 || error.code === 'STORE-TOKEN-MISSING';
                        });
                        if (unauthorized) {
                            this.openLoginModal();
                        }
                    }
                });
        },

        openLoginModal() {
            this.showLoginModal = true;
        },

        loginSuccess() {
            this.showLoginModal = false;
            this.loadLicenses();
        },

        loginAbort() {
            this.showLoginModal = false;
        }
    }
});
