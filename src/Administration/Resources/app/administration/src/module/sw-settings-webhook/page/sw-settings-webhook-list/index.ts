/**
 * @sw-package framework
 */
import type Repository from 'src/core/data/repository.data';
import type EntityCollection from 'src/core/data/entity-collection.data';
import template from './sw-settings-webhook-list.html.twig';
import './sw-settings-webhook-list.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 */
export default Component.wrapComponentConfig({
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    mixins: [Mixin.getByName('listing'), Mixin.getByName('notification')],

    data(): {
        webhooks: EntityCollection<'webhook'> | null;
        roleNames: Record<string, string>;
        integrationApps: Record<string, Entity<'app'>>;
        isLoading: boolean;
        sortBy: string;
        sortDirection: 'ASC' | 'DESC';
    } {
        return {
            webhooks: null,
            roleNames: {},
            integrationApps: {},
            isLoading: false,
            sortBy: 'name',
            sortDirection: 'ASC',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        webhookRepository(): Repository<'webhook'> {
            return this.repositoryFactory.create('webhook');
        },

        aclRoleRepository(): Repository<'acl_role'> {
            return this.repositoryFactory.create('acl_role');
        },

        appRepository(): Repository<'app'> {
            return this.repositoryFactory.create('app');
        },

        webhookCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            if (this.term) {
                criteria.setTerm(this.term);
            }
            criteria.addAssociation('app');
            criteria.addAssociation('ownerUser');
            criteria.addAssociation('ownerIntegration');
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            return criteria;
        },

        columns() {
            return [
                {
                    property: 'name',
                    label: 'sw-settings-webhook.list.columnName',
                    primary: true,
                },
                {
                    property: 'eventName',
                    label: 'sw-settings-webhook.list.columnEvent',
                },
                {
                    property: 'url',
                    label: 'sw-settings-webhook.list.columnUrl',
                },
                {
                    property: 'owner',
                    label: 'sw-settings-webhook.list.columnOwner',
                    sortable: false,
                },
                {
                    property: 'roles',
                    label: 'sw-settings-webhook.list.columnRoles',
                    sortable: false,
                },
                {
                    property: 'active',
                    inlineEdit: 'string',
                    label: 'sw-settings-webhook.list.columnActive',
                    align: 'center',
                },
                {
                    property: 'errorCount',
                    label: 'sw-settings-webhook.list.columnErrorCount',
                    align: 'right',
                },
            ];
        },

        showEmptyState(): boolean {
            return !this.isLoading && this.webhooks?.total === 0;
        },
    },

    methods: {
        async getList(): Promise<void> {
            this.isLoading = true;

            try {
                this.webhooks = await this.webhookRepository.search(this.webhookCriteria);
                this.total = this.webhooks.total ?? 0;
                await this.onUpdateRecords(this.webhooks);
            } finally {
                this.isLoading = false;
            }
        },

        async onUpdateRecords(webhooks: EntityCollection<'webhook'>): Promise<void> {
            await Promise.all([this.loadRoleNames(webhooks), this.loadIntegrationApps(webhooks)]);
        },

        // loaded separately, an ownerIntegration.app association makes the entity hydrator loop between integration and app
        async loadIntegrationApps(webhooks: EntityCollection<'webhook'>): Promise<void> {
            const integrationIds = [
                ...new Set(
                    webhooks
                        .map((webhook) => webhook.ownerIntegrationId)
                        .filter((id): id is EntityKey<'integration'> => !!id),
                ),
            ].filter((integrationId) => !this.integrationApps[integrationId]);

            if (integrationIds.length === 0) {
                return;
            }

            const criteria = new Criteria(1, integrationIds.length);
            criteria.addFilter(Criteria.equalsAny('integrationId', integrationIds));

            const apps = await this.appRepository.search(criteria);
            apps.forEach((app) => {
                this.integrationApps[app.integrationId] = app;
            });
        },

        async loadRoleNames(webhooks: EntityCollection<'webhook'>): Promise<void> {
            const roleIds = [...new Set(webhooks.flatMap((webhook) => (webhook.aclRoleIds ?? []) as string[]))].filter(
                (roleId) => !this.roleNames[roleId],
            );

            if (roleIds.length === 0) {
                return;
            }

            const criteria = new Criteria(1, roleIds.length);
            criteria.addFilter(Criteria.equalsAny('id', roleIds));

            const roles = await this.aclRoleRepository.search(criteria);
            roles.forEach((role) => {
                this.roleNames[role.id] = role.name;
            });
        },

        // the app of a manifest webhook, or of a webhook its app created through the API
        ownerApp(webhook: Entity<'webhook'>): Entity<'app'> | null {
            return (
                webhook.app ?? (webhook.ownerIntegrationId ? this.integrationApps[webhook.ownerIntegrationId] : null) ?? null
            );
        },

        ownerTypeLabel(webhook: Entity<'webhook'>): string {
            if (webhook.app) {
                return this.$t('sw-settings-webhook.list.ownerApp');
            }

            if (webhook.ownerIntegrationId && this.integrationApps[webhook.ownerIntegrationId]) {
                return this.$t('sw-settings-webhook.list.ownerAppIntegration');
            }

            if (webhook.ownerIntegration) {
                return this.$t('sw-settings-webhook.list.ownerIntegration');
            }

            if (webhook.ownerUser) {
                return this.$t('sw-settings-webhook.list.ownerUser');
            }

            return '';
        },

        limitedRoleIds(webhook: Entity<'webhook'>): string[] {
            return (webhook.aclRoleIds ?? []) as string[];
        },

        roleName(roleId: string): string {
            return this.roleNames[roleId] ?? roleId;
        },

        deleteTooltip(webhook: Entity<'webhook'>, allowDelete: boolean) {
            return {
                message: webhook.appId
                    ? this.$t('sw-settings-webhook.list.tooltipAppWebhookNotDeletable')
                    : this.$t('sw-privileges.tooltip.warning'),
                disabled: allowDelete && !webhook.appId,
                showOnDisabledElements: true,
            };
        },

        onInlineEditSave(promise: Promise<void>, webhook: Entity<'webhook'>): void {
            promise
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$t('sw-settings-webhook.list.messageSaveSuccess', { name: webhook.name }),
                    });
                })
                .catch(() => {
                    void this.getList();
                    this.createNotificationError({
                        message: this.$t('sw-settings-webhook.list.messageSaveError', { name: webhook.name }),
                    });
                });
        },

        onDeleteFailed(): void {
            this.createNotificationError({
                message: this.$t('sw-settings-webhook.list.messageDeleteError'),
            });
        },
    },
});
