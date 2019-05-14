import { Component, Application } from 'src/core/shopware';
import template from './sw-dashboard-index.html.twig';
import './sw-dashboard-index.scss';

Component.register('sw-dashboard-index', {
    template,

    inject: ['storeService', 'pluginService'],

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    data() {
        return {
            pluginIsLoading: false,
            pluginIsSaveSuccessful: false
        };
    },

    computed: {
        roadmapLink() {
            return this.$tc('sw-dashboard.welcome.roadmapLink');
        },

        username() {
            if (this.$store.state.adminUser.currentProfile) {
                return this.$store.state.adminUser.currentProfile.firstName;
            }
            return '';
        },
        isPayPalActivated() {
            return Application.getContainer('factory').module.getModuleRegistry().has('swag-paypal');
        },
        isMigrationActivated() {
            return Application.getContainer('factory').module.getModuleRegistry().has('swag-migration');
        }
    },

    methods: {
        onInstallPayPal() {
            this.setupPlugin('SwagPayPal').then(() => {
                document.location.reload();
            });
        },
        onInstallSwagMigration() {
            this.setupPlugin('SwagMigrationAssistant').then(() => {
                document.location.reload();
            });
        },
        installPluginFinish() {
            this.pluginIsSaveSuccessful = false;
        },
        setupPlugin(pluginName) {
            this.pluginIsLoading = true;
            this.pluginIsSaveSuccessful = false;

            return this.storeService.downloadPlugin(pluginName, true)
                .then(() => {
                    this.pluginIsSaveSuccessful = true;
                    return this.pluginService.install(pluginName);
                })
                .then(() => {
                    return this.pluginService.activate(pluginName);
                })
                .then(() => {
                    return this.$store.dispatch('notification/createNotification', {
                        title: pluginName,
                        message: this.$tc('sw-dashboard.plugin.setupSuccessfulMsg', 0, { pluginName }),
                        variant: 'success',
                        growl: true
                    });
                })
                .finally(() => {
                    this.pluginIsLoading = false;
                });
        }
    }
});
