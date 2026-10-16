import { mount, type VueWrapper } from '@vue/test-utils';
import AgenticCommerceCard, { AGENTIC_COMMERCE_EXTENSION_NAME, HIDDEN_CONFIG_KEY } from './index';
import type { Extension } from 'src/module/sw-extension/service/extension-store-action.service';

const EXTENSION_NAME = AGENTIC_COMMERCE_EXTENSION_NAME;

describe('module/sw-dashboard/component/sw-dashboard-agentic-commerce-card', () => {
    let wrapper: VueWrapper | null = null;
    let extensionStoreActionService: {
        getMyExtensions: jest.Mock;
        downloadExtension: jest.Mock;
    };
    let shopwareExtensionService: {
        installAndActivateExtension: jest.Mock;
        activateExtension: jest.Mock;
        checkLogin: jest.Mock;
    };
    let cacheApiService: { clear: jest.Mock };
    let userConfigSearch: jest.SpyInstance;
    let userConfigUpsert: jest.SpyInstance;

    async function createWrapper({
        canMaintainPlugins = true,
        extensions = [],
        dismissed = false,
        extensionManagementDisabled = false,
        failInitialLoad = false,
        storeLoggedIn = true,
    }: {
        canMaintainPlugins?: boolean;
        extensions?: Array<Partial<Extension> & Pick<Extension, 'name'>>;
        dismissed?: boolean;
        extensionManagementDisabled?: boolean;
        failInitialLoad?: boolean;
        storeLoggedIn?: boolean;
    } = {}) {
        userConfigSearch.mockResolvedValue({
            data: {
                [HIDDEN_CONFIG_KEY]: [dismissed],
            },
        });
        if (failInitialLoad) {
            userConfigSearch.mockRejectedValueOnce(new Error('request failed'));
        }
        extensionStoreActionService.getMyExtensions.mockResolvedValue(extensions);
        shopwareExtensionService.checkLogin.mockImplementation(() => {
            Shopware.Store.get('shopwareExtensions').userInfo = storeLoggedIn
                ? { name: 'Store user', email: 'store@example.com', avatarUrl: '' }
                : null;
            return Promise.resolve();
        });
        Shopware.Store.get('context').app.config.settings = {
            appUrlReachable: true,
            appsRequireAppUrl: false,
            disableExtensionManagement: extensionManagementDisabled,
            minSearchTermLength: 1,
        };

        wrapper = mount(AgenticCommerceCard, {
            global: {
                provide: {
                    acl: {
                        can: (privilege: string) => privilege !== 'system.plugin_maintain' || canMaintainPlugins,
                    },
                    extensionStoreActionService,
                    shopwareExtensionService,
                    cacheApiService,
                },
            },
        });

        await flushPromises();
        return wrapper;
    }

    beforeEach(() => {
        extensionStoreActionService = {
            getMyExtensions: jest.fn().mockResolvedValue([]),
            downloadExtension: jest.fn().mockResolvedValue(undefined),
        };
        shopwareExtensionService = {
            installAndActivateExtension: jest.fn().mockResolvedValue(undefined),
            activateExtension: jest.fn().mockResolvedValue(undefined),
            checkLogin: jest.fn().mockResolvedValue(undefined),
        };
        cacheApiService = {
            clear: jest.fn().mockResolvedValue(undefined),
        };
        userConfigSearch = jest.spyOn(Shopware.Service('userConfigService'), 'search');
        userConfigUpsert = jest.spyOn(Shopware.Service('userConfigService'), 'upsert').mockResolvedValue(undefined);
    });

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
        Shopware.Store.get('context').app.config.settings = {
            appUrlReachable: true,
            appsRequireAppUrl: false,
            disableExtensionManagement: false,
            minSearchTermLength: 1,
        };
        Shopware.Store.get('shopwareExtensions').userInfo = null;
        jest.restoreAllMocks();
    });

    it('shows the card when the extension is not installed and the user has not dismissed it', async () => {
        const card = await createWrapper();

        expect(card.find('.sw-dashboard-agentic-commerce-card').exists()).toBe(true);
    });

    it('does not load extension or user data without the extension maintenance privilege', async () => {
        const card = await createWrapper({ canMaintainPlugins: false });

        expect(card.find('.sw-dashboard-agentic-commerce-card').exists()).toBe(false);
        expect(userConfigSearch).not.toHaveBeenCalled();
        expect(extensionStoreActionService.getMyExtensions).not.toHaveBeenCalled();
    });

    it('does not load extension or user data when extension management is disabled', async () => {
        const card = await createWrapper({ extensionManagementDisabled: true });

        expect(card.find('.sw-dashboard-agentic-commerce-card').exists()).toBe(false);
        expect(userConfigSearch).not.toHaveBeenCalled();
        expect(extensionStoreActionService.getMyExtensions).not.toHaveBeenCalled();
    });

    it('keeps the card hidden if initial state cannot be loaded', async () => {
        const consoleError = jest.spyOn(console, 'error').mockImplementation(() => undefined);
        const card = await createWrapper({ failInitialLoad: true });

        expect(card.find('.sw-dashboard-agentic-commerce-card').exists()).toBe(false);
        expect(consoleError).toHaveBeenCalledWith('Failed to load Agentic Commerce dashboard card state.');
    });

    it('hides the card for an active installation', async () => {
        const card = await createWrapper({
            extensions: [{ name: EXTENSION_NAME, installedAt: '2026-10-01T00:00:00.000Z', active: true }],
        });

        expect(card.find('.sw-dashboard-agentic-commerce-card').exists()).toBe(false);
    });

    it('keeps the card hidden after the user dismisses it', async () => {
        const card = await createWrapper({ dismissed: true });

        expect(card.find('.sw-dashboard-agentic-commerce-card').exists()).toBe(false);
    });

    it('persists dismissal for the current user', async () => {
        const card = await createWrapper();

        await card.get('.sw-dashboard-agentic-commerce-card__dismiss').trigger('click');
        await flushPromises();

        expect(userConfigUpsert).toHaveBeenCalledWith({ [HIDDEN_CONFIG_KEY]: [true] });
        expect(card.find('.sw-dashboard-agentic-commerce-card').exists()).toBe(false);
    });

    it('keeps the card visible when dismissal cannot be saved', async () => {
        userConfigUpsert.mockRejectedValueOnce(new Error('network error'));
        const card = await createWrapper();

        await card.get('.sw-dashboard-agentic-commerce-card__dismiss').trigger('click');
        await flushPromises();

        expect(card.find('.sw-dashboard-agentic-commerce-card').exists()).toBe(true);
    });

    it('downloads, installs, activates and reloads for an extension missing from the shop', async () => {
        const card = await createWrapper();
        const reload = jest
            .spyOn(card.vm as unknown as { reloadPage: () => void }, 'reloadPage')
            .mockImplementation(() => undefined);

        await card.get('.sw-dashboard-agentic-commerce-card__actions .mt-button').trigger('click');
        await flushPromises();

        expect(extensionStoreActionService.downloadExtension).toHaveBeenCalledWith(EXTENSION_NAME);
        expect(shopwareExtensionService.installAndActivateExtension).toHaveBeenCalledWith(EXTENSION_NAME, 'plugin');
        expect(cacheApiService.clear).toHaveBeenCalledTimes(1);
        expect(reload).toHaveBeenCalledTimes(1);
    });

    it('does not attempt a download when the Store account is not connected', async () => {
        const card = await createWrapper({ storeLoggedIn: false });
        const createNotificationError = jest.spyOn(
            card.vm as unknown as { createNotificationError: (notification: unknown) => void },
            'createNotificationError',
        );

        await card.get('.sw-dashboard-agentic-commerce-card__actions .mt-button').trigger('click');
        await flushPromises();

        expect(shopwareExtensionService.checkLogin).toHaveBeenCalledTimes(1);
        expect(extensionStoreActionService.downloadExtension).not.toHaveBeenCalled();
        expect(shopwareExtensionService.installAndActivateExtension).not.toHaveBeenCalled();
        expect(createNotificationError).toHaveBeenCalledTimes(1);
    });

    it('ignores a second install click while the first operation is still running', async () => {
        let resolveExtensions: ((extensions: []) => void) | undefined;
        const card = await createWrapper();
        extensionStoreActionService.getMyExtensions.mockImplementationOnce(
            () =>
                new Promise((resolve) => {
                    resolveExtensions = resolve;
                }),
        );

        jest.spyOn(card.vm as unknown as { reloadPage: () => void }, 'reloadPage').mockImplementation(() => undefined);
        const installButton = card.get('.sw-dashboard-agentic-commerce-card__actions .mt-button');
        await installButton.trigger('click');
        await installButton.trigger('click');

        expect(extensionStoreActionService.getMyExtensions).toHaveBeenCalledTimes(2);
        resolveExtensions?.([]);
        await flushPromises();
    });

    it('activates an installed inactive extension without downloading or reinstalling it', async () => {
        extensionStoreActionService.getMyExtensions
            .mockResolvedValueOnce([{ name: EXTENSION_NAME, installedAt: '2026-10-01T00:00:00.000Z', active: false }])
            .mockResolvedValueOnce([{ name: EXTENSION_NAME, installedAt: '2026-10-01T00:00:00.000Z', active: false }]);
        const card = await createWrapper({
            extensions: [{ name: EXTENSION_NAME, installedAt: '2026-10-01T00:00:00.000Z', active: false }],
        });
        const reload = jest
            .spyOn(card.vm as unknown as { reloadPage: () => void }, 'reloadPage')
            .mockImplementation(() => undefined);

        await card.get('.sw-dashboard-agentic-commerce-card__actions .mt-button').trigger('click');
        await flushPromises();

        expect(shopwareExtensionService.activateExtension).toHaveBeenCalledWith(EXTENSION_NAME, 'plugin');
        expect(extensionStoreActionService.downloadExtension).not.toHaveBeenCalled();
        expect(shopwareExtensionService.installAndActivateExtension).not.toHaveBeenCalled();
        expect(reload).toHaveBeenCalledTimes(1);
    });

    it('does not retry installation if cache clearing fails after activation', async () => {
        const card = await createWrapper({
            extensions: [{ name: EXTENSION_NAME, installedAt: '2026-10-01T00:00:00.000Z', active: false }],
        });
        const reload = jest
            .spyOn(card.vm as unknown as { reloadPage: () => void }, 'reloadPage')
            .mockImplementation(() => undefined);
        cacheApiService.clear.mockRejectedValueOnce(new Error('cache clear failed'));

        await card.get('.sw-dashboard-agentic-commerce-card__actions .mt-button').trigger('click');
        await flushPromises();

        expect(shopwareExtensionService.activateExtension).toHaveBeenCalledTimes(1);
        expect(shopwareExtensionService.installAndActivateExtension).not.toHaveBeenCalled();
        expect(reload).not.toHaveBeenCalled();
    });

    it('does not install again after a previous partial install left the plugin downloaded', async () => {
        const card = await createWrapper();
        const consoleError = jest.spyOn(console, 'error').mockImplementation(() => undefined);
        extensionStoreActionService.getMyExtensions
            .mockReset()
            .mockResolvedValueOnce([])
            .mockRejectedValueOnce(new Error('install failed'))
            .mockResolvedValueOnce([{ name: EXTENSION_NAME, installedAt: '2026-10-01T00:00:00.000Z', active: false }]);
        shopwareExtensionService.installAndActivateExtension.mockRejectedValueOnce(new Error('install failed'));
        jest.spyOn(card.vm as unknown as { reloadPage: () => void }, 'reloadPage').mockImplementation(() => undefined);

        await card.get('.sw-dashboard-agentic-commerce-card__actions .mt-button').trigger('click');
        await flushPromises();
        await card.get('.sw-dashboard-agentic-commerce-card__actions .mt-button').trigger('click');
        await flushPromises();

        expect(extensionStoreActionService.downloadExtension).toHaveBeenCalledTimes(1);
        expect(shopwareExtensionService.installAndActivateExtension).toHaveBeenCalledTimes(1);
        expect(shopwareExtensionService.activateExtension).toHaveBeenCalledTimes(1);
        expect(consoleError).toHaveBeenCalledWith('Failed to refresh Agentic Commerce extension state.');
    });
});
