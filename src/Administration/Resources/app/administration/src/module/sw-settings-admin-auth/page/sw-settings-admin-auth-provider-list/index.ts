import { defineComponent } from 'vue';
import template from './sw-settings-admin-auth-provider-list.html.twig';

const { Criteria } = Shopware.Data;

/**
 * Listing of the OIDC providers managed via the admin (`admin_auth_provider` entities).
 *
 * When providers are declared in the YAML configuration (`shopware.admin_auth.providers`),
 * the database providers are ignored by the backend — the listing is replaced by a
 * "managed via configuration file" banner instead of showing entries that would never be used.
 *
 * @sw-package framework
 * @private
 */
export default defineComponent({
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Shopware.Mixin.getByName('notification'),
    ],

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    data() {
        return {
            providers: null as EntityCollection<'admin_auth_provider'> | null,
            isLoading: true,
            sortBy: 'priority',
            sortDirection: 'DESC' as 'ASC' | 'DESC',
        };
    },

    computed: {
        providerRepository() {
            return this.repositoryFactory.create('admin_auth_provider');
        },

        managedByConfig(): boolean {
            return Shopware.Context.app.config.settings?.adminAuth?.managedByConfig === true;
        },

        columns() {
            return [
                {
                    property: 'name',
                    label: this.$t('sw-settings-admin-auth.list.columns.name'),
                    allowResize: true,
                    primary: true,
                },
                {
                    property: 'active',
                    label: this.$t('sw-settings-admin-auth.list.columns.active'),
                    allowResize: true,
                    align: 'center',
                    width: '100px',
                },
                {
                    property: 'priority',
                    label: this.$t('sw-settings-admin-auth.list.columns.priority'),
                    allowResize: true,
                    align: 'right',
                    width: '100px',
                },
            ];
        },

        defaultCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            return criteria;
        },
    },

    created() {
        void this.loadProviders();
    },

    methods: {
        async loadProviders() {
            if (this.managedByConfig) {
                this.isLoading = false;

                return;
            }

            this.isLoading = true;

            try {
                this.providers = await this.providerRepository.search(this.defaultCriteria, Shopware.Context.api);
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-admin-auth.list.loadError'),
                });
            } finally {
                this.isLoading = false;
            }
        },
    },
});
