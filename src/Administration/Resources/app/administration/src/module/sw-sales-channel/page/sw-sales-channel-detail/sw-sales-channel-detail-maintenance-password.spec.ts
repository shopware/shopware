/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';
import { defineComponent, type PropType } from 'vue';

const mockGet = jest.fn();
const mockSave = jest.fn(() => Promise.resolve());

interface MaintenancePassword {
    isEnabled: boolean;
    isSet: boolean;
    value: string;
    error: { code: string; detail?: string } | null;
}

/**
 * Stands in for the routed detail view and receives the same props, so the suite can change the
 * maintenance password state the way the base view does.
 */
const routedViewProbe = defineComponent({
    name: 'routed-view-probe',
    props: {
        maintenancePassword: {
            type: Object as PropType<MaintenancePassword>,
            required: true,
        },
    },
    template: '<div class="routed-view-probe"></div>',
});

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
                'router-view': {
                    setup: () => ({ routedViewProbe }),
                    template: '<slot :Component="routedViewProbe" />',
                },
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

    const maintenancePassword = () => wrapper.getComponent(routedViewProbe).props('maintenancePassword');

    const save = async () => {
        await wrapper.get('.sw-sales-channel-detail__save-action').trigger('click');
        await flushPromises();
    };

    return { maintenancePassword, save, httpClient };
}

describe('src/module/sw-sales-channel/page/sw-sales-channel-detail maintenance password', () => {
    let createNotification: jest.SpyInstance;

    beforeEach(() => {
        mockGet.mockClear();
        mockSave.mockClear();
        createNotification = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('should enable the password checkbox when a password is stored', async () => {
        const { maintenancePassword, httpClient } = await createWrapper({ isSet: true });

        expect(httpClient.get).toHaveBeenCalledWith(maintenancePasswordUrl, { headers: {} });
        expect(maintenancePassword()).toEqual({
            isEnabled: true,
            isSet: true,
            value: '',
            error: null,
        });
    });

    it('should keep the password checkbox disabled when no password is stored', async () => {
        const { maintenancePassword } = await createWrapper();

        expect(maintenancePassword().isEnabled).toBe(false);
        expect(maintenancePassword().isSet).toBe(false);
    });

    it('should require a password when the protection is enabled without a stored password', async () => {
        const { maintenancePassword, save, httpClient } = await createWrapper();
        maintenancePassword().isEnabled = true;

        await save();

        expect(maintenancePassword().error?.code).toBe('c1051bb4-d103-4f74-8988-acbcafc7fdc3');
        expect(mockSave).not.toHaveBeenCalled();
        expect(httpClient.post).not.toHaveBeenCalled();
    });

    it.each([
        ['too short', 'a'.repeat(11)],
        ['too long', 'a'.repeat(4097)],
    ])('should reject a password that is %s without saving', async (_, password) => {
        const { maintenancePassword, save, httpClient } = await createWrapper();
        maintenancePassword().isEnabled = true;
        maintenancePassword().value = password;

        await save();

        expect(maintenancePassword().error?.detail).toBe('sw-sales-channel.detail.maintenancePasswordMinLength');
        expect(mockSave).not.toHaveBeenCalled();
        expect(httpClient.post).not.toHaveBeenCalled();
    });

    it('should save a new password after the sales channel was saved', async () => {
        const { maintenancePassword, save, httpClient } = await createWrapper();
        maintenancePassword().isEnabled = true;
        maintenancePassword().value = 'a-valid-password';

        await save();

        expect(mockSave).toHaveBeenCalledTimes(1);
        expect(httpClient.post).toHaveBeenCalledWith(
            maintenancePasswordUrl,
            { password: 'a-valid-password' },
            { headers: {} },
        );
    });

    it('should keep the stored password when the field is left empty', async () => {
        const { save, httpClient } = await createWrapper({ isSet: true });

        await save();

        expect(mockSave).toHaveBeenCalledTimes(1);
        expect(httpClient.post).not.toHaveBeenCalled();
    });

    it('should remove the stored password when the protection is disabled', async () => {
        const { maintenancePassword, save, httpClient } = await createWrapper({ isSet: true });
        maintenancePassword().isEnabled = false;

        await save();

        expect(httpClient.post).toHaveBeenCalledWith(maintenancePasswordUrl, { password: '' }, { headers: {} });
    });

    it('should show the API error when saving the password fails', async () => {
        const { maintenancePassword, save, httpClient } = await createWrapper();
        httpClient.post.mockRejectedValueOnce({
            response: { data: { errors: [{ detail: 'The sales channel could not be updated.' }] } },
        });
        maintenancePassword().isEnabled = true;
        maintenancePassword().value = 'a-valid-password';

        await save();

        expect(createNotification).toHaveBeenCalledWith(
            expect.objectContaining({ variant: 'error', message: 'The sales channel could not be updated.' }),
        );
    });
});
