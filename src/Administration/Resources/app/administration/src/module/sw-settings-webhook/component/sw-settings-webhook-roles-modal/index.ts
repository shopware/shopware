/**
 * @sw-package framework
 */
import type { PropType } from 'vue';
import type Repository from 'src/core/data/repository.data';
import type { HttpError } from 'src/core/factory/http-client.types';
import template from './sw-settings-webhook-roles-modal.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;

/**
 * @private
 */
export default Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory'],

    emits: [
        'modal-close',
        'saved',
    ],

    mixins: [Mixin.getByName('notification')],

    props: {
        webhook: {
            type: Object as PropType<Entity<'webhook'>>,
            required: true,
        },
    },

    data(): {
        isOpen: boolean;
        isLoading: boolean;
        isSaving: boolean;
        editableWebhook: Entity<'webhook'> | null;
        selectedRoles: EntityCollection<'acl_role'> | null;
    } {
        return {
            isOpen: true,
            isLoading: true,
            isSaving: false,
            editableWebhook: null,
            selectedRoles: null,
        };
    },

    computed: {
        webhookRepository(): Repository<'webhook'> {
            return this.repositoryFactory.create('webhook');
        },

        aclRoleRepository(): Repository<'acl_role'> {
            return this.repositoryFactory.create('acl_role');
        },

        owner(): Entity<'user'> | Entity<'integration'> | null {
            return this.editableWebhook?.ownerUser ?? this.editableWebhook?.ownerIntegration ?? null;
        },

        ownerRoleIds(): string[] {
            return (this.owner?.aclRoles ?? []).map((role) => role.id);
        },

        isOwnerAdmin(): boolean {
            return this.owner?.admin === true;
        },

        roleCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('deletedAt', null));
            criteria.addFilter(Criteria.equals('app.id', null));

            if (!this.isOwnerAdmin) {
                criteria.addFilter(Criteria.equalsAny('id', this.ownerRoleIds));
            }

            return criteria;
        },

        isRoleSelectDisabled(): boolean {
            return !this.isOwnerAdmin && this.ownerRoleIds.length === 0;
        },

        unheldRoleNames(): string {
            if (this.isOwnerAdmin) {
                return '';
            }

            return (this.selectedRoles ?? [])
                .filter((role) => !this.ownerRoleIds.includes(role.id))
                .map((role) => role.name)
                .join(', ');
        },
    },

    created() {
        void this.loadWebhook();
    },

    methods: {
        async loadWebhook(): Promise<void> {
            const criteria = new Criteria(1, 1);
            criteria.addAssociation('ownerUser.aclRoles');
            criteria.addAssociation('ownerIntegration.aclRoles');

            try {
                this.editableWebhook = await this.webhookRepository.get(this.webhook.id, Shopware.Context.api, criteria);
                this.selectedRoles = await this.loadSelectedRoles((this.editableWebhook?.aclRoleIds ?? []) as string[]);
            } finally {
                this.isLoading = false;
            }
        },

        onModalRootChange(isOpen: boolean): void {
            if (!isOpen) {
                this.$emit('modal-close');
            }
        },

        onChangeRoles(roles: EntityCollection<'acl_role'>): void {
            if (!this.editableWebhook) {
                return;
            }

            this.selectedRoles = roles;
            this.editableWebhook.aclRoleIds = roles.length > 0 ? Array.from(roles, (role) => role.id) : undefined;
        },

        async onSave(): Promise<void> {
            if (!this.editableWebhook) {
                return;
            }

            this.isSaving = true;

            try {
                await this.webhookRepository.save(this.editableWebhook);
                this.createNotificationSuccess({
                    message: this.$t('sw-settings-webhook.list.messageSaveSuccess', { name: this.webhook.name }),
                });
                this.$emit('saved');
                this.$emit('modal-close');
            } catch (error) {
                const { response } = error as HttpError<{ errors: ShopwareHttpError[] }>;
                const message = response?.data.errors.some(
                    ({ code }) => code === 'FRAMEWORK__WEBHOOK_EVENT_PRIVILEGES_MISSING',
                )
                    ? 'sw-settings-webhook.roles.messageEventNotCovered'
                    : 'sw-settings-webhook.list.messageSaveError';

                this.createNotificationError({ message: this.$t(message, { name: this.webhook.name }) });
            } finally {
                this.isSaving = false;
            }
        },

        async loadSelectedRoles(roleIds: string[]): Promise<EntityCollection<'acl_role'>> {
            if (roleIds.length === 0) {
                return new EntityCollection(this.aclRoleRepository.route, 'acl_role', Shopware.Context.api);
            }

            const criteria = new Criteria(1, roleIds.length);
            criteria.setIds(roleIds);

            return this.aclRoleRepository.search(criteria);
        },
    },
});
