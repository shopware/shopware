/**
 * @sw-package framework
 */
import template from './sw-oauth-client-list.html.twig';
import './sw-oauth-client-list.scss';

const { Criteria } = Shopware.Data;

/** @private */
export default Shopware.Component.wrapComponentConfig({
    template,
    inject: [
        'repositoryFactory',
        'acl',
    ],
    mixins: [Shopware.Mixin.getByName('notification')],

    data() {
        return {
            clients: null as EntityCollection<'oauth_client'> | null,
            currentClient: null as Entity<'oauth_client'> | null,
            deleteClient: null as Entity<'oauth_client'> | null,
            redirectUrisText: '',
            isNew: false,
            isLoading: false,
            isSaving: false,
            page: 1,
            limit: 25,
            total: 0,
        };
    },

    computed: {
        repository() {
            return this.repositoryFactory.create('oauth_client');
        },

        columns() {
            return [
                { property: 'name', label: this.$t('sw-oauth-client.name'), primary: true },
                { property: 'id', label: this.$t('sw-oauth-client.clientId') },
                { property: 'active', label: this.$t('sw-oauth-client.active') },
            ];
        },

        canSave(): boolean {
            return this.acl.can(this.isNew ? 'oauth_client.creator' : 'oauth_client.editor');
        },
    },

    created() {
        void this.getList();
    },

    methods: {
        async getList() {
            this.isLoading = true;
            try {
                const criteria = new Criteria(this.page, this.limit);
                criteria.addSorting(Criteria.sort('name', 'ASC'));
                this.clients = await this.repository.search(criteria, Shopware.Context.api);
                this.total = this.clients.total ?? 0;
            } catch {
                this.createNotificationError({ message: this.$t('sw-oauth-client.loadError') });
            } finally {
                this.isLoading = false;
            }
        },

        onPageChange({ page, limit }: { page: number; limit: number }) {
            this.page = page;
            this.limit = limit;
            void this.getList();
        },

        onCreate() {
            if (!this.acl.can('oauth_client.creator') || this.isLoading || this.isSaving) return;
            this.currentClient = this.repository.create(Shopware.Context.api);
            this.currentClient.active = true;
            this.currentClient.redirectUris = [];
            this.redirectUrisText = '';
            this.isNew = true;
        },

        async onEdit(client: Entity<'oauth_client'>) {
            if (!this.acl.can('oauth_client.viewer') || this.isLoading) return;
            this.isLoading = true;
            try {
                // Edit a fresh entity so cancelling never changes a row in the list.
                this.currentClient = await this.repository.get(client.id, Shopware.Context.api);
                this.redirectUrisText = (this.currentClient?.redirectUris ?? []).join('\n');
                this.isNew = false;
            } catch {
                this.createNotificationError({ message: this.$t('sw-oauth-client.loadError') });
            } finally {
                this.isLoading = false;
            }
        },

        onClose() {
            if (!this.isSaving) this.currentClient = null;
        },

        async onSave() {
            if (!this.currentClient || !this.canSave || this.isSaving) return;
            this.isSaving = true;
            // Keep each URL unchanged. The DAL rejects invalid values instead of rewriting them.
            this.currentClient.redirectUris = this.redirectUrisText.split(/\r?\n/).filter((uri) => uri !== '');
            try {
                await this.repository.save(this.currentClient, Shopware.Context.api);
                this.currentClient = await this.repository.get(this.currentClient.id, Shopware.Context.api);
                this.isNew = false;
                this.createNotificationSuccess({ message: this.$t('sw-oauth-client.saved') });
                this.currentClient = null;
                await this.getList();
            } catch {
                this.createNotificationError({ message: this.$t('sw-oauth-client.saveError') });
            } finally {
                this.isSaving = false;
            }
        },

        async onDelete() {
            if (!this.deleteClient || !this.acl.can('oauth_client.deleter') || this.isSaving) return;
            this.isSaving = true;
            try {
                await this.repository.delete(this.deleteClient.id, Shopware.Context.api);
                this.deleteClient = null;
                await this.getList();
            } catch {
                this.createNotificationError({ message: this.$t('sw-oauth-client.deleteError') });
            } finally {
                this.isSaving = false;
            }
        },
    },
});
