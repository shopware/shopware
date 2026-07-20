/**
 * @sw-package fundamentals@framework
 */
import template from './sw-integration-list.html.twig';
import './sw-integration-list.scss';

const {
    Mixin,
    Data: { Criteria },
} = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'integrationService',
        'repositoryFactory',
        'acl',
        'feature',
    ],

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
            mcpIntegration: null,
            pendingMcpAllowlist: null,
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
            criteria.addFilter(
                Criteria.multi('OR', [
                    Criteria.equals('app.id', null),
                    Criteria.equals('app.active', true),
                ]),
            );
            criteria.addSorting(Criteria.sort('label', 'ASC'));
            criteria.addAssociation('aclRoles');
            criteria.addAssociation('app');

            return criteria;
        },

        mcpGrantedPrivileges() {
            if (!this.mcpIntegration?.aclRoles) {
                return [];
            }

            return [...new Set(this.mcpIntegration.aclRoles.flatMap((role) => role.privileges ?? []))];
        },

        secretAccessKeyFieldTypeIsText() {
            return this.showSecretAccessKey;
        },

        secretAccessKeyFieldTypeIsPassword() {
            return !this.showSecretAccessKey;
        },

        integrationColumns() {
            return [
                {
                    property: 'label',
                    label: this.$t('sw-integration.list.integrationName'),
                    primary: true,
                },
                {
                    property: 'writeAccess',
                    label: this.$t('sw-integration.list.permissions'),
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

            return this.integrationRepository
                .search(this.integrationCriteria)
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

            const integration = this.integrations.find((a) => a.id === this.currentIntegration.id);

            if (typeof integration === 'undefined') {
                this.createIntegration();
            } else {
                this.updateIntegration(integration);
            }
        },

        updateIntegration(integration) {
            this.isModalLoading = true;
            const shouldSaveAdminFlag = this.shouldSaveAdminFlag(integration);

            this.integrationRepository
                .save(integration)
                .then(() => {
                    return this.updateAdminFlagIfNecessary(integration, shouldSaveAdminFlag);
                })
                .then(() => {
                    return this.getList();
                })
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
            const integration = this.currentIntegration;
            const shouldSaveAdminFlag = this.shouldSaveAdminFlag(integration);

            this.integrationRepository
                .save(integration)
                .then(() => {
                    return this.updateAdminFlagIfNecessary(integration, shouldSaveAdminFlag);
                })
                .then(() => {
                    return this.getList();
                })
                .then(() => {
                    this.createSavedSuccessNotification();
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

        shouldSaveAdminFlag(integration) {
            if (!integration || typeof integration.getOrigin !== 'function') {
                return false;
            }

            const origin = integration.getOrigin();

            return Boolean(origin?.admin) !== Boolean(integration.admin);
        },

        updateAdminFlagIfNecessary(integration, shouldSaveAdminFlag) {
            if (!shouldSaveAdminFlag) {
                return Promise.resolve();
            }

            return this.integrationService.updateAdmin(integration.id, integration.admin);
        },

        createSavedSuccessNotification() {
            this.createNotificationSuccess({
                message: this.$t('sw-integration.detail.messageSaveSuccess'),
            });
        },

        createSavedErrorNotification() {
            this.createNotificationError({
                message: this.$t('sw-integration.detail.messageSaveError'),
            });
        },

        onGenerateKeys() {
            if (!this.currentIntegration) {
                return;
            }

            this.isModalLoading = true;

            this.integrationService
                .generateKey()
                .then((response) => {
                    this.currentIntegration = this.currentIntegration || this.integrationRepository.create();
                    this.currentIntegration.accessKey = response.accessKey;
                    this.currentIntegration.secretAccessKey = response.secretAccessKey;
                    this.showSecretAccessKey = true;
                    this.isModalLoading = false;
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('sw-integration.detail.messageCreateNewError'),
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

        onShowMcpModal(integration) {
            this.mcpIntegration = integration;
            this.pendingMcpAllowlist = integration.mcpAllowlist ? { ...integration.mcpAllowlist } : null;
        },

        onCloseMcpModal() {
            this.mcpIntegration = null;
            this.pendingMcpAllowlist = null;
        },

        onSaveMcpAllowlist() {
            if (!this.mcpIntegration) {
                return;
            }

            this.integrationService
                .saveMcpAllowlist(this.mcpIntegration.id, this.pendingMcpAllowlist)
                .then(() => {
                    this.mcpIntegration.mcpAllowlist = this.pendingMcpAllowlist;
                    this.createSavedSuccessNotification();
                    this.onCloseMcpModal();
                })
                .catch(() => {
                    this.createSavedErrorNotification();
                });
        },

        isAppIntegration(integration) {
            return !!integration.app;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = null;
        },

        onConfirmDelete(id) {
            if (!id) {
                return;
            }

            this.onCloseDeleteModal();

            this.integrationRepository.delete(id).then(() => {
                this.getList();
            });
        },
    },
};
