/**
 * @package system-settings
 */
import template from './sw-integration-list.html.twig';
import './sw-integration-list.scss';

const { Mixin, Data: { Criteria } } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['integrationService', 'repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            integrations: null,
            isLoading: false,
            isModalLoading: false,
            showDeleteModal: null,
            currentIntegration: null,
            showSecretAccessKey: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        integrationRepository() {
            return this.repositoryFactory.create('integration');
        },

        integrationCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.equals('deletedAt', null));
            criteria.addFilter(Criteria.equals('app.id', null));
            criteria.addSorting(Criteria.sort('label', 'ASC'));
            criteria.addAssociation('aclRoles');

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
                    primary: true,
                }, {
                    property: 'writeAccess',
                    label: this.$tc('sw-integration.list.permissions'),
                },
            ];
        },
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

            this.integrationRepository.search(this.integrationCriteria)
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

            this.integrationRepository.save(integration)
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

            this.integrationRepository.save(this.currentIntegration)
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
                message: this.$tc('sw-integration.detail.messageSaveSuccess'),
            });
        },

        createSavedErrorNotification() {
            this.createNotificationError({
                message: this.$tc('sw-integration.detail.messageSaveError'),
            });
        },

        onGenerateKeys() {
            if (!this.currentIntegration) {
                return;
            }

            this.isModalLoading = true;

            this.integrationService.generateKey().then((response) => {
                this.currentIntegration = this.currentIntegration || this.integrationRepository.create();
                this.currentIntegration.accessKey = response.accessKey;
                this.currentIntegration.secretAccessKey = response.secretAccessKey;
                this.showSecretAccessKey = true;
                this.isModalLoading = false;
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('sw-integration.detail.messageCreateNewError'),
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

            this.integrationRepository.delete(id)
                .then(() => {
                    this.getList();
                });
        },
    },
};
