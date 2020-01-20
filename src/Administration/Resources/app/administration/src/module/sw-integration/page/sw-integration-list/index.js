import template from './sw-integration-list.html.twig';
import './sw-integration-list.scss';

const { Component, Mixin, Data: { Criteria } } = Shopware;
/** @deprecated tag:v6.4.0 */
const { StateDeprecated } = Shopware;

Component.register('sw-integration-list', {
    template,

    inject: ['integrationService', 'repositoryFactory'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            integrations: null,
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
        /** @deprecated tag:v6.4.0 */
        id() {
            return this.$vnode.tag;
        },

        /** @deprecated tag:v6.4.0 */
        integrationStore() {
            return StateDeprecated.getStore('integration');
        },

        integrationRepository() {
            return this.repositoryFactory.create('integration');
        },

        integrationCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addSorting(Criteria.sort('label', 'ASC'));

            return criteria;
        },

        secretAccessKeyFieldType() {
            return this.showSecretAccessKey ? 'text' : 'password';
        },

        integrationColumns() {
            return [
                {
                    property: 'label',
                    label: this.$tc('sw-integration.list.integrationName'),
                    primary: true
                }, {
                    property: 'writeAccess',
                    label: this.$tc('sw-integration.list.permissions')
                }
            ];
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getList();
        },

        getList() {
            this.isLoading = true;

            this.integrationRepository.search(this.integrationCriteria, Shopware.Context.api)
                .then((integrations) => {
                    this.integrations = integrations;
                })
                .finally(() => {
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
            this.isModalLoading = true;

            this.integrationRepository.save(integration, Shopware.Context.api)
                .then(() => {
                    this.createSavedSuccessNotification();
                    this.onCloseDetailModal();
                })
                .catch(() => {
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

            this.integrationRepository.save(this.currentIntegration, Shopware.Context.api)
                .then(() => {
                    this.createSavedSuccessNotification();
                    this.getList();
                })
                .catch(() => {
                    this.createSavedErrorNotification();
                })
                .finally(() => {
                    this.$nextTick(() => {
                        this.onCloseDetailModal();
                    });
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

        onShowDetailModal(integration) {
            this.currentIntegration = integration;
        },

        onCreateIntegration() {
            this.currentIntegration = this.integrationRepository.create();

            this.onGenerateKeys();
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
                return;
            }

            this.onCloseDeleteModal();

            this.integrationRepository.delete(id, Shopware.Context.api)
                .then(() => {
                    this.getList();
                });
        }
    }
});
