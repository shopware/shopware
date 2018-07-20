import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-integration-list.html.twig';
import './sw-integration-list.less';

Component.register('sw-integration-list', {
    inject: ['integrationService'],

    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            integrations: [],
            isLoading: false,
            showDeleteModal: null,
            currentIntegration: null,
            showSecretAccessKey: false
        };
    },

    computed: {
        integrationStore() {
            return State.getStore('integration');
        },
        secretAccessKeyFieldType() {
            return this.showSecretAccessKey ? 'text' : 'password';
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;

            this.integrationStore.getList({ offset: 0, limit: 100 }).then((response) => {
                this.integrations = response.items;
                this.isLoading = false;
            });
        },

        onSaveIntegration() {
            if (!this.currentIntegration) {
                return;
            }
            const integration = this.integrations.find(a => a.id === this.currentIntegration.id);

            if (typeof integration === 'undefined') {
                this.createIntegration();
            } else {
                this.updateIntegration(integration);
            }
        },

        updateIntegration(integration) {
            Object.assign(integration, this.currentIntegration);
            integration.save().then(() => {
                this.createSavedSuccessNotification();
            }).catch(() => {
                this.createSavedErrorNotification();
            }).finally(() => {
                this.onCloseDetailModal();
            });
        },

        createIntegration() {
            if (!this.currentIntegration.label || this.currentIntegration.label.length < 0) {
                this.createSavedErrorNotification();
                return;
            }

            this.currentIntegration.save().then(() => {
                this.createSavedSuccessNotification();
                this.integrations.push(this.currentIntegration);
            }).catch(() => {
                this.createSavedErrorNotification();
            }).finally(() => {
                this.currentIntegration.isNew = false;
                this.onCloseDetailModal();
            });
        },

        createSavedSuccessNotification() {
            this.createNotificationSuccess({
                title: this.$tc('sw-integration.detail.titleSaveSuccess'),
                message: this.$tc('sw-integration.detail.messageSaveSuccess')
            });
        },

        createSavedErrorNotification() {
            this.createNotificationError({
                title: this.$tc('sw-integration.detail.titleSaveError'),
                message: this.$tc('sw-integration.detail.messageSaveError')
            });
        },

        onGenerateKeys() {
            if (!this.currentIntegration) {
                return;
            }

            this.integrationService.generateKey().then((response) => {
                this.currentIntegration.accessKey = response.accessKey;
                this.currentIntegration.secretAccessKey = response.secretAccessKey;
                this.showSecretAccessKey = true;
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-integration.detail.titleCreateNewError'),
                    message: this.$tc('sw-integration.detail.messageCreateNewError')
                });
            });
        },

        onShowDetailModal(id) {
            if (!id) {
                this.currentIntegration = this.integrationStore.create();
                this.onGenerateKeys();
                return;
            }

            const entry = this.integrations.find(a => a.id === id);
            this.currentIntegration = {};
            Object.assign(this.currentIntegration, entry);
        },

        onCloseDetailModal() {
            this.currentIntegration = null;
            this.showSecretAccessKey = false;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = null;
        },

        onConfirmDelete(id) {
            if (!id) {
                return false;
            }

            return this.integrationStore.store[id].delete(true).then(() => {
                this.integrations = this.integrations.filter(a => a.id !== id);
                this.onCloseDeleteModal();
            });
        }
    }
});
