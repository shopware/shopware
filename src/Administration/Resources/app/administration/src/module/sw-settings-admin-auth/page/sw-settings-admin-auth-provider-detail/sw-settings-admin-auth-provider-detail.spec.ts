/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

interface ProviderConfig {
    clientId?: string;
    clientSecret?: string;
    discoveryUrl?: string;
    issuer?: string;
    authorizationEndpoint?: string;
    tokenEndpoint?: string;
    jwksUri?: string;
    scopes?: string[];
    roleMapping?: Record<string, string[]>;
}

interface DetailVm {
    isCreateMode: boolean;
    isEditable: boolean;
    provider: { name?: string; config: ProviderConfig };
    providerConfig: ProviderConfig;
    clientSecret: string;
    roleOptions: { value: string }[];
    onSave(): Promise<void>;
    onDiscover(): Promise<void>;
    onRoleMappingChange(mapping: Record<string, string[]>): void;
}

function createProvider() {
    return {
        id: 'provider-id',
        name: 'Okta',
        type: 'oidc',
        active: true,
        priority: 1,
        config: {
            clientId: 'client-id',
            discoveryUrl: 'https://idp.example.com/.well-known/openid-configuration',
            scopes: ['openid'],
            roleMapping: { 'idp-admins': ['admin'] },
        },
    };
}

async function createWrapper({
    providerId = null,
    privileges = [
        'admin_auth.viewer',
        'admin_auth.editor',
        'admin_auth.creator',
    ],
    provider = createProvider(),
    discoverOidc = jest.fn(),
}: {
    providerId?: string | null;
    privileges?: string[];
    provider?: ReturnType<typeof createProvider>;
    discoverOidc?: jest.Mock;
} = {}) {
    const repositoryMocks = {
        create: jest.fn(() => ({})),
        get: jest.fn(() => Promise.resolve(provider)),
        save: jest.fn(() => Promise.resolve()),
        search: jest.fn(() =>
            Promise.resolve([
                { name: 'catalog-editor' },
                { name: 'support' },
            ]),
        ),
    };

    const wrapper = mount(await wrapTestComponent('sw-settings-admin-auth-provider-detail', { sync: true }), {
        props: {
            providerId,
        },
        global: {
            mocks: {
                $router: { push: jest.fn() },
            },
            provide: {
                repositoryFactory: {
                    create: () => repositoryMocks,
                },
                adminAuthService: {
                    discoverOidc,
                },
                acl: {
                    can: (identifier: string) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
            },
            stubs: {
                'sw-page': {
                    template: `
                        <div class="sw-page">
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                            <slot></slot>
                        </div>
                    `,
                },
                'sw-card-view': {
                    template: '<div class="sw-card-view"><slot></slot></div>',
                },
                'sw-multi-select': true,
                'sw-multi-tag-select': true,
                'sw-settings-admin-auth-role-mapping': true,
            },
        },
    });

    await flushPromises();

    const vm = wrapper.vm as unknown as DetailVm;

    return { wrapper, vm, repositoryMocks, discoverOidc };
}

describe('module/sw-settings-admin-auth/page/sw-settings-admin-auth-provider-detail', () => {
    afterEach(() => {
        Shopware.Store.get('context').app.config.settings = undefined;
    });

    it('should create a new provider with sensible OIDC defaults', async () => {
        const { vm } = await createWrapper();

        expect(vm.isCreateMode).toBe(true);
        expect(vm.provider).toMatchObject({
            type: 'oidc',
            active: true,
            isPrimary: true,
            isSecondFactor: false,
            priority: 1,
            config: {
                scopes: [
                    'openid',
                    'profile',
                    'email',
                ],
                autoProvision: false,
                roleMapping: {},
                defaultRoles: [],
            },
        });
    });

    it('should load an existing provider and keep the client secret field empty', async () => {
        const { vm, repositoryMocks } = await createWrapper({ providerId: 'provider-id' });

        expect(repositoryMocks.get).toHaveBeenCalledWith('provider-id', Shopware.Context.api);
        expect(vm.provider.name).toBe('Okta');
        expect(vm.clientSecret).toBe('');
    });

    it('should not include a clientSecret in the payload when none was typed', async () => {
        const { vm, repositoryMocks } = await createWrapper({ providerId: 'provider-id' });

        await vm.onSave();
        await flushPromises();

        const savedProvider = (repositoryMocks.save.mock.calls[0] as unknown[])[0] as { config: ProviderConfig };
        expect(savedProvider.config).not.toHaveProperty('clientSecret');
    });

    it('should only send the clientSecret when the admin typed a new one and reset the field afterwards', async () => {
        const { vm, repositoryMocks } = await createWrapper({ providerId: 'provider-id' });

        vm.clientSecret = 'new-secret';
        await vm.onSave();
        await flushPromises();

        const savedProvider = (repositoryMocks.save.mock.calls[0] as unknown[])[0] as { config: ProviderConfig };
        expect(savedProvider.config.clientSecret).toBe('new-secret');
        expect(vm.clientSecret).toBe('');
    });

    it('should fill the endpoints from the discovery document', async () => {
        const discoverOidc = jest.fn(() =>
            Promise.resolve({
                issuer: 'https://idp.example.com',
                authorizationEndpoint: 'https://idp.example.com/authorize',
                tokenEndpoint: 'https://idp.example.com/token',
                jwksUri: 'https://idp.example.com/jwks',
                scopes: [
                    'openid',
                    'email',
                ],
            }),
        );

        const { vm } = await createWrapper({ providerId: 'provider-id', discoverOidc });

        await vm.onDiscover();
        await flushPromises();

        expect(discoverOidc).toHaveBeenCalledWith({
            discoveryUrl: 'https://idp.example.com/.well-known/openid-configuration',
        });
        expect(vm.providerConfig).toMatchObject({
            issuer: 'https://idp.example.com',
            authorizationEndpoint: 'https://idp.example.com/authorize',
            tokenEndpoint: 'https://idp.example.com/token',
            jwksUri: 'https://idp.example.com/jwks',
            scopes: [
                'openid',
                'email',
            ],
        });
    });

    it('should not call the discovery endpoint without a discovery URL', async () => {
        const provider = createProvider();
        provider.config.discoveryUrl = '';

        const { vm, discoverOidc } = await createWrapper({ providerId: 'provider-id', provider });

        const notificationSpy = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');

        await vm.onDiscover();

        expect(discoverOidc).not.toHaveBeenCalled();
        expect(notificationSpy).toHaveBeenCalledWith(expect.objectContaining({ variant: 'error' }));

        notificationSpy.mockRestore();
    });

    it('should offer the acl roles plus the admin pseudo-role as role options', async () => {
        const { vm } = await createWrapper({ providerId: 'provider-id' });

        expect(vm.roleOptions.map((option) => option.value)).toEqual([
            'admin',
            'catalog-editor',
            'support',
        ]);
    });

    it('should be read-only when the providers are managed via the YAML configuration', async () => {
        const appConfig = Shopware.Store.get('context').app.config;
        appConfig.settings = {
            adminAuth: {
                managedByConfig: true,
                adminUiDisabled: false,
            },
        } as unknown as typeof appConfig.settings;

        const { wrapper, vm } = await createWrapper({ providerId: 'provider-id' });

        expect(vm.isEditable).toBe(false);
        expect(wrapper.find('.sw-settings-admin-auth-provider-detail__managed-banner').exists()).toBe(true);
        expect(wrapper.get('.sw-settings-admin-auth-provider-detail__save-action').attributes('disabled')).toBeDefined();
    });

    it('should update the role mapping on the provider config when the editor emits', async () => {
        const { vm } = await createWrapper({ providerId: 'provider-id' });

        vm.onRoleMappingChange({ 'idp-catalog': ['catalog-editor'] });

        expect(vm.provider.config.roleMapping).toEqual({ 'idp-catalog': ['catalog-editor'] });
    });
});
