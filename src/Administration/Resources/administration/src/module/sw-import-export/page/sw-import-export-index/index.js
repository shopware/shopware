import template from './sw-import-export-index.html.twig';
import './sw-import-export-index.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-import-export-index', {
    template,

    inject: ['importExportService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: false,
            selectedImportExpireDate: new Date(new Date().setDate(new Date().getDate() + 90)).toISOString(),
            selectedExportExpireDate: new Date(new Date().setDate(new Date().getDate() + 90)).toISOString(),
            selectedImportFile: null,
            selectedImportProfile: null,
            selectedExportProfile: null,
            processingLog: null,
            features: {
                fileTypes: [],
                uploadFileSizeLimit: null
            }
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadFeatures();
        },

        loadFeatures() {
            this.importExportService.getFeatures().then((response) => {
                this.features = response;
            });
        },

        onStartImportClicked() {
            if (!this.selectedImportProfile) {
                this.createNotificationError({
                    title: this.$tc('sw-import-export-index.cards.import.notification.error.title'),
                    message: this.$tc(
                        'sw-import-export-index.cards.import.notification.error.messages.noProfileSelected'
                    )
                });

                return;
            }

            this.importExportService.initiate(
                this.selectedImportProfile,
                this.selectedImportExpireDate,
                this.selectedImportFile
            ).then((response) => {
                this.processingLog = response.log;
            }).catch((exception) => {
                if (!exception.response || !exception.response.data || !exception.response.data.errors) {
                    return;
                }

                const errors = exception.response.data.errors;
                errors.forEach((error) => {
                    this.createNotificationError({
                        title: this.$tc('sw-import-export-index.cards.import.notification.initFailed.title'),
                        message: this.$tc(
                            `sw-import-export-index.cards.import.notification.initFailed.messages.${error.code}`
                        )
                    });
                });
            });
        },

        onStartExportClicked() {
            if (!this.selectedExportProfile) {
                this.createNotificationError({
                    title: this.$tc('sw-import-export-index.cards.export.notification.error.title'),
                    message: this.$tc(
                        'sw-import-export-index.cards.export.notification.error.messages.noProfileSelected'
                    )
                });

                return;
            }

            this.importExportService.initiate(
                this.selectedExportProfile,
                this.selectedExportExpireDate
            ).then((response) => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-import-export-index.cards.export.notification.initSuccess.title'),
                    message: this.$tc('sw-import-export-index.cards.export.notification.initSuccess.message')
                });
                this.processingLog = response.log;
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-import-export-index.cards.export.notification.initFailed.title'),
                    message: this.$tc('sw-import-export-index.cards.export.notification.initFailed.message')
                });
            });
        }
    }
});
