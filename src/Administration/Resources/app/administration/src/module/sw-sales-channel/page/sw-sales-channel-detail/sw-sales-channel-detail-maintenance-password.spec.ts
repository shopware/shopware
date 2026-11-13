/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';

const mockGet = jest.fn();
const mockSave = jest.fn(() => Promise.resolve());

/**
 * The part of the `sw-sales-channel-detail` page this suite drives. The page is a JavaScript component,
 * so `vm` carries no usable type on its own.
 */
interface SalesChannelDetailVm {
    maintenancePassword: {
        isEnabled: boolean;
        isSet: boolean;
        value: string;
        error: { code: string; detail?: string } | null;
    };
    onSave(): Promise<void>;
    createNotificationError(notification: { message: string }): void;
}

const salesChannelId = '1a2b3c4d';
const maintenancePasswordUrl = `/_action/sales-channel/${salesChannelId}/maintenance-password`;

async function createWrapper({ isSet = false }: { isSet?: boolean } = {}) {
    mockGet.mockResolvedValue({
        id: salesChannelId,
        typeId: Shopware.Defaults.storefrontSalesChannelTypeId,
        name: 'Storefront',
        customerGroupId: 'customer-group-id',
        currencyId: 'currency-id',
        languageId: 'language-id',
        paymentMethodId: 'payment-method-id',
        shippingMethodId: 'shipping-method-id',
        countryId: 'country-id',
        navigationCategoryId: 'navigation-category-id',
        productExports: {
            first: () => ({}),
        },
    });

    const httpClient = {
        get: jest.fn(() => Promise.resolve({ data: { isSet } })),
        post: jest.fn(() => Promise.resolve({})),
    };

    const wrapper = mount(await wrapTestComponent('sw-sales-channel-detail', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template:
                        '<div class="sw-page"><slot name="smart-bar-actions"></slot><slot name="content"></slot></div>',
                },
                'sw-button-process': true,
                'sw-language-switch': true,
                'sw-card-view': {
                    template: '<div class="sw-card-view"><slot /></div>',
                },
                'sw-language-info': true,
                'sw-tabs': true,
                'sw-tabs-item': true,
                'mt-tabs': true,
                'router-view': true,
                'sw-skeleton': true,
                'mt-banner': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        create: () => ({}),
                        get: mockGet,
                        search: () => Promise.resolve([]),
                        delete: () => Promise.resolve(),
                        save: mockSave,
                        buildHeaders: () => ({}),
                        httpClient,
                    }),
                },
                exportTemplateService: {
                    getProductExportTemplateRegistry: () => ({}),
                },
                systemConfigApiService: {
                    getConfig: () => Promise.resolve([]),
                    getValues: () => Promise.resolve({}),
                    batchSave: () => Promise.resolve(),
                },
                customFieldDataProviderService: {
                    getCustomFieldSets: () => Promise.resolve([]),
                },
            },
            mocks: {
                $route: {
                    params: { id: salesChannelId },
                    name: '',
                },
                $router: {
                    push: jest.fn(),
                },
            },
        },
    });

    await flushPromises();

    return { vm: wrapper.vm as unknown as SalesChannelDetailVm, httpClient };
}

describe('src/module/sw-sales-channel/page/sw-sales-channel-detail maintenance password', () => {
    beforeEach(() => {
        mockGet.mockClear();
        mockSave.mockClear();
    });

    it('should enable the password checkbox when a password is stored', async () => {
        const { vm, httpClient } = await createWrapper({ isSet: true });

        expect(httpClient.get).toHaveBeenCalledWith(maintenancePasswordUrl, { headers: {} });
        expect(vm.maintenancePassword).toEqual({
            isEnabled: true,
            isSet: true,
            value: '',
            error: null,
        });
    });

    it('should keep the password checkbox disabled when no password is stored', async () => {
        const { vm } = await createWrapper();

        expect(vm.maintenancePassword.isEnabled).toBe(false);
        expect(vm.maintenancePassword.isSet).toBe(false);
    });

    it('should require a password when the protection is enabled without a stored password', async () => {
        const { vm, httpClient } = await createWrapper();
        vm.maintenancePassword.isEnabled = true;

        await vm.onSave();

        expect(vm.maintenancePassword.error?.code).toBe('c1051bb4-d103-4f74-8988-acbcafc7fdc3');
        expect(mockSave).not.toHaveBeenCalled();
        expect(httpClient.post).not.toHaveBeenCalled();
    });

    it.each([
        ['too short', 'a'.repeat(11)],
        ['too long', 'a'.repeat(4097)],
    ])('should reject a password that is %s without saving', async (_, password) => {
        const { vm, httpClient } = await createWrapper();
        vm.maintenancePassword.isEnabled = true;
        vm.maintenancePassword.value = password;

        await vm.onSave();

        expect(vm.maintenancePassword.error?.detail).toBe('sw-sales-channel.detail.maintenancePasswordMinLength');
        expect(mockSave).not.toHaveBeenCalled();
        expect(httpClient.post).not.toHaveBeenCalled();
    });

    it('should save a new password after the sales channel was saved', async () => {
        const { vm, httpClient } = await createWrapper();
        vm.maintenancePassword.isEnabled = true;
        vm.maintenancePassword.value = 'a-valid-password';

        await vm.onSave();

        expect(mockSave).toHaveBeenCalledTimes(1);
        expect(httpClient.post).toHaveBeenCalledWith(
            maintenancePasswordUrl,
            { password: 'a-valid-password' },
            { headers: {} },
        );
    });

    it('should keep the stored password when the field is left empty', async () => {
        const { vm, httpClient } = await createWrapper({ isSet: true });

        await vm.onSave();

        expect(mockSave).toHaveBeenCalledTimes(1);
        expect(httpClient.post).not.toHaveBeenCalled();
    });

    it('should remove the stored password when the protection is disabled', async () => {
        const { vm, httpClient } = await createWrapper({ isSet: true });
        vm.maintenancePassword.isEnabled = false;

        await vm.onSave();

        expect(httpClient.post).toHaveBeenCalledWith(maintenancePasswordUrl, { password: '' }, { headers: {} });
    });

    it('should show the API error when saving the password fails', async () => {
        const { vm, httpClient } = await createWrapper();
        const notifyError = jest.spyOn(vm, 'createNotificationError');
        httpClient.post.mockRejectedValueOnce({
            response: { data: { errors: [{ detail: 'The sales channel could not be updated.' }] } },
        });
        vm.maintenancePassword.isEnabled = true;
        vm.maintenancePassword.value = 'a-valid-password';

        await vm.onSave();

        expect(notifyError).toHaveBeenCalledWith({ message: 'The sales channel could not be updated.' });
    });
});
