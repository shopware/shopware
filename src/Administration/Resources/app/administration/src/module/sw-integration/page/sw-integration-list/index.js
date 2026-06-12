/**
 * @sw-package fundamentals@framework
 */
import template from './sw-integration-list.html.twig';
import './sw-integration-list.scss';

const {
    Mixin,
    Data: { Criteria },
} = Shopware;

const DEFAULT_LIMIT = 25;

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
            page: 1,
            limit: DEFAULT_LIMIT,
            sortBy: 'label',
            sortDirection: 'ASC',
            searchTerm: '',
            appliedIntegrationFilters: [],
            hasLoadedLargeIntegrationList: false,
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
            const criteria = new Criteria(this.page, this.limit);

            criteria.addFilter(Criteria.equals('deletedAt', null));
            criteria.addFilter(
                Criteria.multi('OR', [
                    Criteria.equals('app.id', null),
                    Criteria.equals('app.active', true),
                ]),
            );
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));
            criteria.addAssociation('aclRoles');
            criteria.addAssociation('app');

            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);
            }

            this.integrationFilterCriteria.forEach((filter) => {
                criteria.addFilter(filter);
            });

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
                    renderer: 'text',
                    primary: true,
                    position: 0,
                    sortable: true,
                },
                {
                    property: 'permissions',
                    label: this.$t('sw-integration.list.permissions'),
                    renderer: 'text',
                    position: 1,
                },
            ];
        },

        hasIntegrations() {
            return this.integrations?.length > 0;
        },

        integrationTotalItems() {
            return this.integrations?.total ?? this.integrations?.length ?? 0;
        },

        hasActiveIntegrationTableCriteria() {
            return this.searchTerm.length > 0 || this.appliedIntegrationFilters.length > 0;
        },

        showIntegrationTableControls() {
            return (
                this.hasLoadedLargeIntegrationList ||
                this.integrationTotalItems > DEFAULT_LIMIT ||
                this.hasActiveIntegrationTableCriteria
            );
        },

        showIntegrationPagination() {
            return this.showIntegrationTableControls;
        },

        integrationFilters() {
            if (!this.showIntegrationTableControls) {
                return [];
            }

            return [
                {
                    id: 'permissions',
                    label: this.$t('sw-integration.list.permissions'),
                    type: {
                        id: 'options',
                        options: [
                            {
                                id: 'admin',
                                label: this.$t('sw-users-permissions.users.user-detail.labelAdministrator'),
                            },
                            {
                                id: 'read',
                                label: this.$t('sw-integration.list.readAccess'),
                            },
                        ],
                    },
                },
            ];
        },

        integrationFilterCriteria() {
            const permissionFilter = this.appliedIntegrationFilters.find((filter) => filter.id === 'permissions');
            const permissionOptions = permissionFilter?.type?.options?.map((option) => option.id) ?? [];

            if (permissionOptions.length !== 1) {
                return [];
            }

            return [
                Criteria.equals('admin', permissionOptions.includes('admin')),
            ];
        },

        canViewIntegration() {
            return this.acl.can('integration.viewer') || this.acl.can('integration.editor');
        },

        deleteIntegration() {
            if (!this.showDeleteModal || !this.integrations) {
                return null;
            }

            return this.integrations.find((integration) => integration.id === this.showDeleteModal) ?? null;
        },

        integrationContextButtons() {
            if (!this.feature.isActive('MCP_SERVER') || !this.acl.can('integration_mcp.editor')) {
                return [];
            }

            return [
                {
                    key: 'edit-mcp',
                    label: this.$t('sw-integration.list.contextMenuEditMcp'),
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

                    if (!this.hasActiveIntegrationTableCriteria && this.integrationTotalItems > DEFAULT_LIMIT) {
                        this.hasLoadedLargeIntegrationList = true;
                    }
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
            if (this.isAppIntegration(integration)) {
                return;
            }

            this.currentIntegration = integration;
        },

        onShowDeleteModal(integration) {
            if (this.isAppIntegration(integration)) {
                return;
            }

            this.showDeleteModal = integration.id;
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

        onIntegrationContextSelect({ key, data }) {
            if (key === 'edit-mcp') {
                this.onShowMcpModal(data);
            }
        },

        isAppIntegration(integration) {
            return !!integration?.app;
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

        onPageChange(page) {
            this.page = page;
            this.getList();
        },

        onLimitChange(limit) {
            this.limit = limit;
            this.page = 1;
            this.getList();
        },

        onSearchValueChange(searchTerm) {
            this.searchTerm = searchTerm;
            this.page = 1;
            this.getList();
        },

        onUpdateAppliedFilters(filters) {
            this.appliedIntegrationFilters = filters;
            this.page = 1;
            this.getList();
        },

        onSortChange(sortBy, sortDirection) {
            this.sortBy = sortBy;
            this.sortDirection = sortDirection;
            this.getList();
        },
    },
};
