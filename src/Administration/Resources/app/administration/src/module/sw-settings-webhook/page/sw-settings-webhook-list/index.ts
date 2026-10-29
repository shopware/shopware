/**
 * @sw-package framework
 */
import type { RouteLocationRaw } from 'vue-router';
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
        roleNames: Record<string, string | null>;
        integrationApps: Record<string, Entity<'app'>>;
        isLoading: boolean;
        hasLoadError: boolean;
        deliveriesWebhook: Entity<'webhook'> | null;
        secretWebhook: Entity<'webhook'> | null;
        sortBy: string;
        sortDirection: 'ASC' | 'DESC';
    } {
        return {
            webhooks: null,
            roleNames: {},
            integrationApps: {},
            isLoading: false,
            hasLoadError: false,
            deliveriesWebhook: null,
            secretWebhook: null,
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
                    inlineEdit: 'boolean',
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
                this.webhooks = await this.searchWebhooks();
                this.hasLoadError = !this.webhooks;
                this.total = this.webhooks?.total ?? 0;

                if (this.webhooks) {
                    await this.onUpdateRecords(this.webhooks);
                }
            } finally {
                this.isLoading = false;
            }
        },

        async searchWebhooks(): Promise<EntityCollection<'webhook'> | null> {
            try {
                return await this.webhookRepository.search(this.webhookCriteria);
            } catch {
                return null;
            }
        },

        onListingSearched(webhooks: EntityCollection<'webhook'>): void {
            if (webhooks === this.webhooks) {
                return;
            }

            this.webhooks = webhooks;
            this.total = webhooks.total ?? 0;
            void this.onUpdateRecords(webhooks);
        },

        async onUpdateRecords(webhooks: EntityCollection<'webhook'>): Promise<void> {
            await Promise.all([this.loadRoleNames(webhooks), this.loadIntegrationApps(webhooks)]);
        },

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
            criteria.setTotalCountMode(0);

            const apps = await this.appRepository.search(criteria);
            apps.forEach((app) => {
                this.integrationApps[app.integrationId] = app;
            });
        },

        async loadRoleNames(webhooks: EntityCollection<'webhook'>): Promise<void> {
            const roleIds = [...new Set(webhooks.flatMap((webhook) => (webhook.aclRoleIds ?? []) as string[]))].filter(
                (roleId) => !(roleId in this.roleNames),
            );

            if (roleIds.length === 0) {
                return;
            }

            const criteria = new Criteria(1, roleIds.length);
            criteria.addFilter(Criteria.equalsAny('id', roleIds));
            criteria.setTotalCountMode(0);

            const roles = await this.aclRoleRepository.search(criteria);
            roleIds.forEach((roleId) => {
                this.roleNames[roleId] = roles.find((role) => role.id === roleId)?.name ?? null;
            });
        },

        ownerApp(webhook: Entity<'webhook'>): Entity<'app'> | null {
            return (
                webhook.app ?? (webhook.ownerIntegrationId ? this.integrationApps[webhook.ownerIntegrationId] : null) ?? null
            );
        },

        isAppInactive(webhook: Entity<'webhook'>): boolean {
            return this.ownerApp(webhook)?.active === false;
        },

        isSent(webhook: Entity<'webhook'>): boolean {
            return webhook.active === true && !this.isAppInactive(webhook);
        },

        inactiveTooltip(webhook: Entity<'webhook'>) {
            return {
                message: this.$t('sw-settings-webhook.list.tooltipAppInactive'),
                disabled: !this.isAppInactive(webhook),
            };
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

        ownerName(webhook: Entity<'webhook'>): string {
            if (webhook.ownerUser) {
                return webhook.ownerUser.username;
            }

            return this.ownerApp(webhook)?.translated?.label ?? webhook.ownerIntegration?.label ?? '';
        },

        ownerRoute(webhook: Entity<'webhook'>): RouteLocationRaw | null {
            if (webhook.ownerUser) {
                return this.acl.can('users_and_permissions.viewer')
                    ? { name: 'sw.users.permissions.user.detail', params: { id: webhook.ownerUser.id } }
                    : null;
            }

            if (this.ownerApp(webhook)) {
                return this.acl.can('system.plugin_maintain') ? { name: 'sw.extension.my-extensions.listing.app' } : null;
            }

            if (webhook.ownerIntegration) {
                return this.acl.can('integration.viewer') ? { name: 'sw.integration.index' } : null;
            }

            return null;
        },

        limitedRoleIds(webhook: Entity<'webhook'>): string[] {
            return (webhook.aclRoleIds ?? []) as string[];
        },

        isRoleDeleted(roleId: string): boolean {
            return this.roleNames[roleId] === null;
        },

        roleName(roleId: string): string {
            if (this.isRoleDeleted(roleId)) {
                return this.$t('sw-settings-webhook.list.deletedRole');
            }

            return this.roleNames[roleId] ?? '';
        },

        roleRoute(roleId: string): RouteLocationRaw | null {
            if (!this.acl.can('users_and_permissions.viewer') || this.isRoleDeleted(roleId)) {
                return null;
            }

            return { name: 'sw.users.permissions.role.detail', params: { id: roleId } };
        },

        canDelete(webhook: Entity<'webhook'>, allowDelete: boolean): boolean {
            return allowDelete && !webhook.appId;
        },

        deleteTooltip(webhook: Entity<'webhook'>, allowDelete: boolean) {
            return {
                message: webhook.appId
                    ? this.$t('sw-settings-webhook.list.tooltipAppWebhookNotDeletable')
                    : this.$t('sw-privileges.tooltip.warning'),
                disabled: this.canDelete(webhook, allowDelete),
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

        openDeliveries(webhook: Entity<'webhook'>): void {
            this.deliveriesWebhook = webhook;
        },

        closeDeliveries(): void {
            this.deliveriesWebhook = null;
        },

        canHaveOwnSecret(webhook: Entity<'webhook'>): boolean {
            return !this.ownerApp(webhook);
        },

        openSecret(webhook: Entity<'webhook'>): void {
            this.secretWebhook = webhook;
        },

        closeSecret(): void {
            this.secretWebhook = null;
        },

        onDeleteFailed(): void {
            this.createNotificationError({
                message: this.$t('sw-settings-webhook.list.messageDeleteError'),
            });
        },
    },
});
