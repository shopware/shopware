/**
 * @sw-package fundamentals@framework
 */

import swSettingsUcpDetail from './index';

const defaultConfig = () => ({
    active: true,
    ucpVersion: '2026-01-23',
    continueUrlTemplate: '{domainUrl}/checkout',
    signaturePolicy: 'strict',
    idempotencyRequired: true,
    enabledTransports: [
        'rest',
        'mcp',
    ],
    enabledCapabilities: [
        'dev.ucp.shopping.catalog.search',
        'dev.ucp.shopping.cart',
    ],
});

const defaultKeys = () => [{ kid: 'kid-1', algorithm: 'ES256', status: 'active', activatedAt: '2026-05-01' }];

function createComponent(overrides = {}) {
    const ucpAdminService = {
        getConfig: jest.fn(() => Promise.resolve(defaultConfig())),
        listKeys: jest.fn(() => Promise.resolve({ items: defaultKeys() })),
        writeConfig: jest.fn(() => Promise.resolve()),
        createKey: jest.fn(() => Promise.resolve({ kid: 'kid-2' })),
        retireKey: jest.fn(() => Promise.resolve()),
        previewProfile: jest.fn(() => Promise.resolve({ version: '2026-01-23' })),
        ...overrides.ucpAdminService,
    };

    const salesChannelRepo = {
        get: jest.fn(() => Promise.resolve({ name: 'Storefront DE' })),
    };

    const repositoryFactory = {
        create: jest.fn(() => salesChannelRepo),
    };

    const component = {
        ...swSettingsUcpDetail.data(),
        salesChannelId: 'sc-1',
        ucpAdminService,
        repositoryFactory,
        createNotificationError: jest.fn(),
        createNotificationSuccess: jest.fn(),
        $t: (key) => key,
    };

    Object.entries(swSettingsUcpDetail.computed).forEach(
        ([
            name,
            getter,
        ]) => {
            Object.defineProperty(component, name, { get: getter.bind(component), configurable: true });
        },
    );
    Object.entries(swSettingsUcpDetail.methods).forEach(
        ([
            name,
            fn,
        ]) => {
            component[name] = fn.bind(component);
        },
    );

    return { component, ucpAdminService };
}

describe('module/sw-settings-ucp/page/sw-settings-ucp-detail', () => {
    it('exposes the expected reactive data shape', () => {
        const data = swSettingsUcpDetail.data();

        expect(data).toMatchObject({
            isLoading: true,
            isSaving: false,
            isRotatingKey: false,
            config: null,
            keys: [],
            profilePreview: null,
            activeTab: 'general',
            salesChannelName: '',
        });
        expect(data.capabilityGroups).toBeInstanceOf(Array);
        expect(data.allTransports).toBeInstanceOf(Array);
    });

    it('exposes the four capability groups: catalog, commerce, extensions, identity', () => {
        const data = swSettingsUcpDetail.data();
        const groupIds = data.capabilityGroups.map((g) => g.id);

        expect(groupIds).toEqual([
            'catalog',
            'commerce',
            'extensions',
            'identity',
        ]);
    });

    it('loadAll fetches sales channel, config and keys in parallel', async () => {
        const { component, ucpAdminService } = createComponent();

        await component.loadAll();

        expect(ucpAdminService.getConfig).toHaveBeenCalledWith('sc-1');
        expect(ucpAdminService.listKeys).toHaveBeenCalledWith('sc-1');
        expect(component.salesChannelName).toBe('Storefront DE');
        expect(component.config.active).toBe(true);
        expect(component.keys).toHaveLength(1);
        expect(component.isLoading).toBe(false);
    });

    it('isEnabled returns true for enabled capabilities and false otherwise', async () => {
        const { component } = createComponent();
        await component.loadAll();

        expect(component.isEnabled('dev.ucp.shopping.cart')).toBe(true);
        expect(component.isEnabled('dev.ucp.shopping.unknown')).toBe(false);
    });

    it('toggleCapability adds or removes a capability without mutating the original list', async () => {
        const { component } = createComponent();
        await component.loadAll();

        component.toggleCapability('dev.ucp.shopping.checkout');
        expect(component.config.enabledCapabilities).toContain('dev.ucp.shopping.checkout');

        component.toggleCapability('dev.ucp.shopping.cart');
        expect(component.config.enabledCapabilities).not.toContain('dev.ucp.shopping.cart');
    });

    it('save persists the config and reloads', async () => {
        const { component, ucpAdminService } = createComponent();
        await component.loadAll();
        ucpAdminService.getConfig.mockClear();

        await component.save();

        expect(ucpAdminService.writeConfig).toHaveBeenCalledWith('sc-1', expect.objectContaining({ active: true }));
        expect(component.createNotificationSuccess).toHaveBeenCalled();
        expect(ucpAdminService.getConfig).toHaveBeenCalled();
        expect(component.isSaving).toBe(false);
    });

    it('rotateKey calls createKey with rotate=true and refreshes the key list', async () => {
        const { component, ucpAdminService } = createComponent();
        await component.loadAll();
        ucpAdminService.listKeys.mockClear();

        await component.rotateKey();

        expect(ucpAdminService.createKey).toHaveBeenCalledWith('sc-1', { algorithm: 'ES256', rotate: true });
        expect(component.createNotificationSuccess).toHaveBeenCalled();
        expect(ucpAdminService.listKeys).toHaveBeenCalledWith('sc-1');
        expect(component.isRotatingKey).toBe(false);
    });

    it('retireKey calls retireKey and refreshes the key list', async () => {
        const { component, ucpAdminService } = createComponent();
        await component.loadAll();
        ucpAdminService.listKeys.mockClear();

        await component.retireKey('kid-1');

        expect(ucpAdminService.retireKey).toHaveBeenCalledWith('sc-1', 'kid-1');
        expect(ucpAdminService.listKeys).toHaveBeenCalledWith('sc-1');
    });

    it('loadProfilePreview populates the profilePreview', async () => {
        const { component, ucpAdminService } = createComponent();
        await component.loadAll();

        await component.loadProfilePreview();

        expect(ucpAdminService.previewProfile).toHaveBeenCalledWith('sc-1');
        expect(component.profilePreview).toEqual({ version: '2026-01-23' });
    });
});
