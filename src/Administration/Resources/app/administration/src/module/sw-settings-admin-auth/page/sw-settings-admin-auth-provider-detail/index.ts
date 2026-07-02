import { defineComponent } from 'vue';
import template from './sw-settings-admin-auth-provider-detail.html.twig';
import './sw-settings-admin-auth-provider-detail.scss';

const { Criteria } = Shopware.Data;

/**
 * Shape of the `config` JSON on `admin_auth_provider`, matching the camelCase keys the
 * backend's DalProviderLoader reads. `clientSecret` is write-only: the backend strips it
 * from every read (ProviderSecretSubscriber), so it must only be part of the payload when
 * the admin typed a new one.
 */
interface ProviderConfig {
    clientId?: string;
    clientSecret?: string;
    discoveryUrl?: string;
    issuer?: string;
    authorizationEndpoint?: string;
    tokenEndpoint?: string;
    jwksUri?: string;
    scopes?: string[];
    autoProvision?: boolean;
    groupsClaim?: string;
    roleMapping?: Record<string, string[]>;
    defaultRoles?: string[];
}

interface RoleOption {
    value: string;
    label: string;
}

/**
 * The reserved pseudo-role that toggles the `user.admin` flag instead of an acl_role
 * assignment (see the backend's SsoRoleSynchronizer::ADMIN_PSEUDO_ROLE).
 */
const ADMIN_PSEUDO_ROLE = 'admin';

/**
 * Create and detail page for OIDC providers (`admin_auth_provider`).
 *
 * @sw-package framework
 * @private
 */
export default defineComponent({
    template,

    inject: [
        'repositoryFactory',
        'adminAuthService',
        'acl',
    ],

    mixins: [
        Shopware.Mixin.getByName('notification'),
    ],

    props: {
        providerId: {
            type: String,
            required: false,
            default: null,
        },
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    data() {
        return {
            provider: null as Entity<'admin_auth_provider'> | null,
            /**
             * Write-only: stays empty when editing and is only added to the config payload
             * when the admin typed a new secret.
             */
            clientSecret: '',
            roleOptions: [] as RoleOption[],
            isLoading: false,
            isSaveSuccessful: false,
            isDiscovering: false,
        };
    },

    computed: {
        providerRepository() {
            return this.repositoryFactory.create('admin_auth_provider');
        },

        roleRepository() {
            return this.repositoryFactory.create('acl_role');
        },

        isCreateMode(): boolean {
            return !this.providerId;
        },

        managedByConfig(): boolean {
            return Shopware.Context.app.config.settings?.adminAuth?.managedByConfig === true;
        },

        isEditable(): boolean {
            if (this.managedByConfig) {
                return false;
            }

            return this.acl.can(this.isCreateMode ? 'admin_auth.creator' : 'admin_auth.editor');
        },

        providerConfig(): ProviderConfig {
            return (this.provider?.config ?? {}) as ProviderConfig;
        },
    },

    created() {
        if (this.isCreateMode) {
            this.createProvider();
        } else {
            void this.loadProvider();
        }

        void this.loadRoleOptions();
    },

    methods: {
        createProvider() {
            const provider = this.providerRepository.create(Shopware.Context.api);

            provider.type = 'oidc';
            provider.active = true;
            provider.isPrimary = true;
            provider.isSecondFactor = false;
            provider.priority = 1;
            provider.config = {
                scopes: [
                    'openid',
                    'profile',
                    'email',
                ],
                autoProvision: false,
                roleMapping: {},
                defaultRoles: [],
            };

            this.provider = provider;
        },

        async loadProvider() {
            this.isLoading = true;

            try {
                const provider = await this.providerRepository.get(this.providerId, Shopware.Context.api);

                if (!provider) {
                    throw new Error(`Provider "${this.providerId}" not found`);
                }

                this.ensureConfigObject(provider);

                this.provider = provider;
                // clientSecret is write-only and never returned by the API: always start empty
                this.clientSecret = '';
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-admin-auth.detail.loadError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * The selectable role names for the role mapping and default roles: every acl_role
         * plus the reserved 'admin' pseudo-role. The backend maps role NAMES (not ids).
         */
        async loadRoleOptions() {
            const criteria = new Criteria(1, 500);
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            try {
                const roles = await this.roleRepository.search(criteria, Shopware.Context.api);

                const options: RoleOption[] = [
                    {
                        value: ADMIN_PSEUDO_ROLE,
                        label: this.$t('sw-settings-admin-auth.detail.adminPseudoRoleLabel'),
                    },
                ];

                roles.forEach((role) => {
                    if (role.name && role.name !== ADMIN_PSEUDO_ROLE) {
                        options.push({ value: role.name, label: role.name });
                    }
                });

                this.roleOptions = options;
            } catch {
                // The role options are optional sugar; the plain inputs still work without them.
                this.roleOptions = [];
            }
        },

        onRoleMappingChange(mapping: Record<string, string[]>) {
            if (!this.provider) {
                return;
            }

            this.ensureConfigObject(this.provider);
            (this.provider.config as ProviderConfig).roleMapping = mapping;
        },

        async onDiscover() {
            const config = this.providerConfig;
            const issuer = (config.discoveryUrl ?? config.issuer ?? '').trim();

            if (!issuer) {
                this.createNotificationError({
                    message: this.$t('sw-settings-admin-auth.detail.discoverMissingUrl'),
                });

                return;
            }

            this.isDiscovering = true;

            try {
                const discovered = await this.adminAuthService.discoverOidc({ discoveryUrl: issuer });

                config.issuer = discovered.issuer;
                config.authorizationEndpoint = discovered.authorizationEndpoint;
                config.tokenEndpoint = discovered.tokenEndpoint;
                config.jwksUri = discovered.jwksUri;
                if (Array.isArray(discovered.scopes) && discovered.scopes.length > 0) {
                    config.scopes = discovered.scopes;
                }

                this.createNotificationSuccess({
                    message: this.$t('sw-settings-admin-auth.detail.discoverSuccess'),
                });
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-admin-auth.detail.discoverError'),
                });
            } finally {
                this.isDiscovering = false;
            }
        },

        async onSave() {
            if (!this.provider) {
                return;
            }

            this.isLoading = true;
            this.isSaveSuccessful = false;

            this.ensureConfigObject(this.provider);
            const config = this.provider.config as ProviderConfig;

            // Only send a clientSecret when the admin typed one — otherwise leave it out of
            // the payload so the stored (encrypted) secret is preserved by the backend.
            if (this.clientSecret.length > 0) {
                config.clientSecret = this.clientSecret;
            } else {
                delete config.clientSecret;
            }

            try {
                await this.providerRepository.save(this.provider, Shopware.Context.api);
                this.isSaveSuccessful = true;
                this.clientSecret = '';

                if (this.isCreateMode) {
                    void this.$router.push({
                        name: 'sw.settings.admin.auth.detail',
                        params: { id: this.provider.id },
                    });

                    return;
                }

                await this.loadProvider();
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-admin-auth.detail.saveError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        onCancel() {
            void this.$router.push({ name: 'sw.settings.admin.auth.providers' });
        },

        ensureConfigObject(provider: Entity<'admin_auth_provider'>) {
            if (!provider.config || typeof provider.config !== 'object') {
                provider.config = {};
            }
        },
    },
});
