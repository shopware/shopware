/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';

const mockSave = jest.fn(() => Promise.resolve());
const mockGet = jest.fn(() =>
    Promise.resolve({
        id: '1a2b3c4d',
        analyticsId: '1a2b3c',
        analytics: {
            id: '1a2b3c',
            trackingId: 'tracking-id',
        },
        productExports: {
            first: () => ({}),
        },
    }),
);
const mockGetSystemConfig = jest.fn(() => Promise.resolve([]));
const mockGetSystemConfigValues = jest.fn(() => Promise.resolve({}));

async function createWrapper(routeParamsOrLegacyArg = { id: '1a2b3c4d' }) {
    const routeParams = Array.isArray(routeParamsOrLegacyArg) ? { id: '1a2b3c4d' } : routeParamsOrLegacyArg;

    return mount(await wrapTestComponent('sw-sales-channel-detail', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: `
    <div class="sw-page">
        <slot name="smart-bar-actions"></slot>
    </div>
                    `,
                },
                'sw-button-process': {
                    template: '<button class="sw-button-process"></button>',
                    props: ['disabled'],
                },
                'sw-language-switch': true,
                'sw-card-view': true,
                'sw-language-info': true,
                'sw-tabs': true,
                'sw-tabs-item': true,
                'router-view': true,
                'sw-skeleton': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        create: () => ({}),
                        get: mockGet,
                        search: () => Promise.resolve([]),
                        delete: () => Promise.resolve(),
                        save: mockSave,
                    }),
                },
                exportTemplateService: {
                    getProductExportTemplateRegistry: () => ({}),
                },
                systemConfigApiService: {
                    getConfig: mockGetSystemConfig,
                    getValues: mockGetSystemConfigValues,
                    batchSave: () => Promise.resolve(),
                },
            },
            mocks: {
                $route: {
                    params: routeParams,
                    name: '',
                },
            },
        },
    });
}

describe('src/module/sw-sales-channel/page/sw-sales-channel-detail', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
        mockSave.mockClear();
        mockGet.mockClear();
        mockGetSystemConfig.mockClear();
        mockGetSystemConfigValues.mockClear();
    });

    it('should disable the save button when privilege does not exist', async () => {
        const wrapper = await createWrapper();
        const saveButton = wrapper.getComponent('.sw-sales-channel-detail__save-action');

        await wrapper.setData({
            isLoading: false,
        });

        expect(saveButton.props('disabled')).toBe(true);
    });

    it('should enable the save button when privilege does exists', async () => {
        global.activeAclRoles = ['sales_channel.editor'];
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoading: false,
        });

        const saveButton = wrapper.getComponent('.sw-sales-channel-detail__save-action');

        expect(saveButton.props('disabled')).toBe(false);
    });

    it('should remove analytics association on save when analyticsId is empty', async () => {
        const wrapper = await createWrapper([
            'sales_channel.editor',
        ]);

        await wrapper.setData({
            isLoading: false,
        });

        wrapper.vm.salesChannel.analytics.trackingId = null;

        const analyticsId = wrapper.vm.updateAnalytics();

        expect(typeof analyticsId).toBe('string');
        expect(wrapper.vm.salesChannel.analyticsId).toBeNull();
        expect(wrapper.vm.salesChannel.analytics).toBeUndefined();
    });

    it('should not remove analytics association on save when analyticsId is not empty', async () => {
        global.activeAclRoles = ['sales_channel.editor'];
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoading: false,
        });

        const analyticsId = wrapper.vm.updateAnalytics();

        expect(typeof analyticsId).toBe('string');
        expect(wrapper.vm.salesChannel.analyticsId).toBe('1a2b3c');
        expect(wrapper.vm.salesChannel.analytics.id).toEqual(wrapper.vm.salesChannel.analyticsId);
    });

    it('should have currency criteria with sort', async () => {
        const wrapper = await createWrapper();

        const criteria = wrapper.vm.getLoadSalesChannelCriteria();

        expect(criteria.parse()).toEqual(
            expect.objectContaining({
                associations: expect.objectContaining({
                    currencies: expect.objectContaining({
                        sort: expect.arrayContaining([
                            {
                                field: 'name',
                                order: 'ASC',
                                naturalSorting: false,
                            },
                        ]),
                    }),
                }),
            }),
        );
    });

    it('should provide agentic commerce export config accessor for child views', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            agenticCommerceExportConfig: [
                {
                    provider: 'open-ai',
                    elements: [],
                    values: {},
                    isLoading: false,
                },
            ],
        });

        const provide = wrapper.vm.$options.provide.call(wrapper.vm);

        expect(typeof provide.swSalesChannelDetailGetAgenticCommerceExportConfig).toBe('function');
        expect(provide.swSalesChannelDetailGetAgenticCommerceExportConfig()).toEqual(wrapper.vm.agenticCommerceExportConfig);
    });

    it('should load agentic commerce export config in create flow when route has typeId but no id', async () => {
        mockGetSystemConfig.mockResolvedValueOnce([
            {
                elements: [
                    {
                        name: 'core.openAiProductExport.returnPolicyUrl',
                    },
                ],
            },
        ]);

        const wrapper = await createWrapper({
            typeId: Shopware.Defaults.agenticCommerceTypeId,
        });

        wrapper.vm.salesChannel = {
            id: 'new-sales-channel-id',
            typeId: Shopware.Defaults.agenticCommerceTypeId,
        };

        await wrapper.vm.loadEntityData();
        await flushPromises();

        expect(mockGetSystemConfig).toHaveBeenCalledWith('core.openAiProductExport');
        expect(mockGetSystemConfigValues).toHaveBeenCalledWith('core.openAiProductExport', 'new-sales-channel-id');
        expect(wrapper.vm.agenticCommerceExportConfig[0].elements).toHaveLength(1);
    });

    it('should save without reloading entity data when saveOnLanguageChange is called', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        mockGet.mockClear();

        await wrapper.vm.saveOnLanguageChange();
        await flushPromises();

        expect(mockSave).toHaveBeenCalledTimes(1);
        expect(mockGet).not.toHaveBeenCalled();
    });

    it('should save and reload entity data when onSave is called', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        mockGet.mockClear();

        await wrapper.vm.onSave();
        await flushPromises();

        expect(mockSave).toHaveBeenCalledTimes(1);
        expect(mockGet).toHaveBeenCalledTimes(1);
    });

    it('should handle errors in saveOnLanguageChange without reloading entity data', async () => {
        mockSave.mockRejectedValueOnce(new Error('Save failed'));

        const wrapper = await createWrapper();
        await flushPromises();

        mockGet.mockClear();

        await wrapper.vm.saveOnLanguageChange();
        await flushPromises();

        expect(wrapper.vm.isSaveSuccessful).toBe(false);
        expect(wrapper.vm.isLoading).toBe(false);
        expect(mockGet).not.toHaveBeenCalled();
    });

    it('should handle errors in onSave without reloading entity data', async () => {
        mockSave.mockRejectedValueOnce(new Error('Save failed'));

        const wrapper = await createWrapper();
        await flushPromises();

        mockGet.mockClear();

        await wrapper.vm.onSave();
        await flushPromises();

        expect(wrapper.vm.isSaveSuccessful).toBe(false);
        expect(wrapper.vm.isLoading).toBe(false);
        expect(mockGet).not.toHaveBeenCalled();
    });
});
