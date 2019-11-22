import template from './sw-integration-list.html.twig';
import './sw-integration-list.scss';

const { Component, Mixin, StateDeprecated } = Shopware;

Component.register('sw-integration-list', {
    template,

    inject: ['integrationService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('listing')
    ],

    data() {
        return {
            integrations: [],
            isLoading: false,
            isModalLoading: false,
            showDeleteModal: null,
            currentIntegration: null,
            showSecretAccessKey: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        id() {
            return this.$vnode.tag;
        },
        integrationStore() {
            return StateDeprecated.getStore('integration');
        },
        secretAccessKeyFieldType() {
            return this.showSecretAccessKey ? 'text' : 'password';
        }
    },

    methods: {
        getList() {
            this.isLoading = true;
            const params = this.getListingParams();

            this.integrations = [];

            // Use the integration label as the default sorting
            if (!params.sortBy && !params.sortDirection) {
                params.sortBy = 'label';
                params.sortDirection = 'ASC';
            }

            return this.integrationStore.getList(params).then((response) => {
                this.total = response.total;
                this.integrations = response.items;
                this.isLoading = false;

                return this.integrations;
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
            this.isModalLoading = true;
            Object.assign(integration, this.currentIntegration);
            integration.save().then(() => {
                this.createSavedSuccessNotification();
                this.onCloseDetailModal();
            }).catch(() => {
                this.createSavedErrorNotification();
                this.onCloseDetailModal();
            });
        },

        createIntegration() {
            if (!this.currentIntegration.label || !this.currentIntegration.label.length) {
                this.createSavedErrorNotification();
                return;
            }

            this.isModalLoading = true;

            this.currentIntegration.save().then(() => {
                this.createSavedSuccessNotification();
                this.getList();
                this.currentIntegration.isNew = false;

                this.$nextTick(() => {
                    this.onCloseDetailModal();
                });
            }).catch(() => {
                this.createSavedErrorNotification();
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
            this.isModalLoading = true;

            this.integrationService.generateKey().then((response) => {
                this.currentIntegration.accessKey = response.accessKey;
                this.currentIntegration.secretAccessKey = response.secretAccessKey;
                this.showSecretAccessKey = true;
                this.isModalLoading = false;
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
            this.isModalLoading = false;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = null;
        },

        onConfirmDelete(id) {
            if (!id) {
                return false;
            }

            this.onCloseDeleteModal();

            return this.integrationStore.store[id].delete(true).then(() => {
                this.getList();
            });
        }
    }
});
