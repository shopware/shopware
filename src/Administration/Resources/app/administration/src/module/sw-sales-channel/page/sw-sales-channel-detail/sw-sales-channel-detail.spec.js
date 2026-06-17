/* eslint-disable sw-test-rules/test-file-max-lines-warning */

/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';

const mockSave = jest.fn(() => Promise.resolve());
const mockGet = jest.fn();
const mockGetSystemConfig = jest.fn(() => Promise.resolve([]));
const mockGetSystemConfigValues = jest.fn(() => Promise.resolve({}));

const defaultSalesChannelResponse = {
    id: '1a2b3c4d',
    typeId: Shopware.Defaults.storefrontSalesChannelTypeId,
    analyticsId: '1a2b3c',
    analytics: {
        id: '1a2b3c',
        trackingId: 'tracking-id',
    },
    productExports: {
        first: () => ({}),
    },
};

async function createWrapper(optionsOrLegacyArg = { id: '1a2b3c4d' }) {
    const normalizedOptions = Array.isArray(optionsOrLegacyArg)
        ? { routeParams: { id: '1a2b3c4d' } }
        : optionsOrLegacyArg.routeParams || optionsOrLegacyArg.salesChannelResponse
          ? optionsOrLegacyArg
          : { routeParams: optionsOrLegacyArg };

    const { routeParams = { id: '1a2b3c4d' }, salesChannelResponse = {} } = normalizedOptions;

    mockGet.mockResolvedValue({
        ...defaultSalesChannelResponse,
        ...salesChannelResponse,
        analytics: {
            ...defaultSalesChannelResponse.analytics,
            ...(salesChannelResponse.analytics ?? {}),
        },
        productExports: salesChannelResponse.productExports ?? defaultSalesChannelResponse.productExports,
    });

    return mount(await wrapTestComponent('sw-sales-channel-detail', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: `
    <div class="sw-page">
        <slot name="smart-bar-actions"></slot>
        <slot name="content"></slot>
    </div>
                    `,
                },
                'sw-button-process': {
                    template: '<button class="sw-button-process"></button>',
                    props: ['disabled'],
                },
                'sw-language-switch': true,
                'sw-card-view': {
                    template: '<div class="sw-card-view"><slot /></div>',
                },
                'sw-language-info': true,
                'sw-tabs': {
                    template: '<div class="sw-tabs"><slot /></div>',
                },
                'sw-tabs-item': {
                    template: '<div class="sw-tabs-item"><slot /></div>',
                    props: [
                        'route',
                        'title',
                        'disabled',
                    ],
                },
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

    it.each([
        [
            'paymentMethods',
            'distinguishableName',
        ],
        [
            'shippingMethods',
            'name',
        ],
        [
            'countries',
            'name',
        ],
        [
            'currencies',
            'name',
        ],
        [
            'languages',
            'name',
        ],
    ])('should load %s association with alphabetical sort', async (associationName, sortField) => {
        await createWrapper();

        const criteria = mockGet.mock.calls[0][2];
        expect(criteria.parse().associations[associationName].sort[0]).toEqual({
            field: sortField,
            order: 'ASC',
            naturalSorting: false,
        });
    });

    it('should load languages association with active language filter', async () => {
        await createWrapper();

        const criteria = mockGet.mock.calls[0][2];
        expect(criteria.parse().associations.languages.filter).toEqual([
            { type: 'equals', field: 'active', value: true },
        ]);
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

    it('shows the insights tab for agentic commerce channels and hides storefront analytics', async () => {
        const wrapper = await createWrapper({
            routeParams: {
                id: '1a2b3c4d',
            },
            salesChannelResponse: {
                typeId: Shopware.Defaults.agenticCommerceTypeId,
            },
        });

        await flushPromises();

        expect(wrapper.text()).toContain('sw-sales-channel.detail.productExport.tabInsights');
        expect(wrapper.text()).not.toContain('sw-sales-channel.detail.tabAnalytics');
    });

    it('shows storefront analytics tab for storefront channels and hides insights', async () => {
        const wrapper = await createWrapper({
            routeParams: {
                id: '1a2b3c4d',
            },
            salesChannelResponse: {
                typeId: Shopware.Defaults.storefrontSalesChannelTypeId,
            },
        });

        await flushPromises();

        expect(wrapper.text()).toContain('sw-sales-channel.detail.tabAnalytics');
        expect(wrapper.text()).toContain('sw-sales-channel.detail.tabAgenticFiles');
        expect(wrapper.text()).not.toContain('sw-sales-channel.detail.productExport.tabInsights');

        const tabs = wrapper.findAll('.sw-tabs-item');
        expect(tabs[tabs.length - 1].text()).toContain('sw-sales-channel.detail.tabAgenticFiles');
    });

    it('shows agentic files tab for headless sales channels', async () => {
        const wrapper = await createWrapper({
            routeParams: {
                id: '1a2b3c4d',
            },
            salesChannelResponse: {
                typeId: Shopware.Defaults.apiSalesChannelTypeId,
            },
        });

        await flushPromises();

        expect(wrapper.text()).toContain('sw-sales-channel.detail.tabAgenticFiles');
    });

    it('hides the insights tab for product comparison channels', async () => {
        const wrapper = await createWrapper({
            salesChannelResponse: {
                typeId: Shopware.Defaults.productComparisonTypeId,
            },
        });

        await flushPromises();

        expect(wrapper.text()).not.toContain('sw-sales-channel.detail.productExport.tabInsights');
        expect(wrapper.text()).not.toContain('sw-sales-channel.detail.tabAgenticFiles');
    });

    it('returns true for isProductExportChannel on product comparison and agentic channels', async () => {
        const agenticWrapper = await createWrapper({
            salesChannelResponse: {
                typeId: Shopware.Defaults.agenticCommerceTypeId,
            },
        });

        await flushPromises();

        expect(agenticWrapper.vm.isProductExportChannel).toBe(true);

        const comparisonWrapper = await createWrapper({
            salesChannelResponse: {
                typeId: Shopware.Defaults.productComparisonTypeId,
            },
        });

        await flushPromises();

        expect(comparisonWrapper.vm.isProductExportChannel).toBe(true);
        agenticWrapper.unmount();
        comparisonWrapper.unmount();
    });

    it('returns false for isProductExportChannel on storefront channels', async () => {
        const wrapper = await createWrapper({
            salesChannelResponse: {
                typeId: Shopware.Defaults.storefrontSalesChannelTypeId,
            },
        });

        await flushPromises();

        expect(wrapper.vm.isProductExportChannel).toBe(false);
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

    it('should detect current template on load when product export bodyTemplate matches', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const template = { name: 'open_ai', bodyTemplate: '{{ feedRow|json_encode }}' };
        wrapper.vm.productComparison.templates = { open_ai: template };
        wrapper.vm.productComparison.templateOptions = [template];
        wrapper.vm.salesChannel.productExports = {
            first: () => ({ bodyTemplate: template.bodyTemplate }),
        };

        wrapper.vm.detectCurrentTemplate();

        expect(wrapper.vm.productComparison.templateName).toBe('open_ai');
    });

    it('should not detect a template when bodyTemplate does not match any registered template', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const template = { name: 'open_ai', bodyTemplate: '{{ feedRow|json_encode }}' };
        wrapper.vm.productComparison.templates = { open_ai: template };
        wrapper.vm.productComparison.templateOptions = [template];
        wrapper.vm.salesChannel.productExports = {
            first: () => ({ bodyTemplate: '<custom>{{ product.name }}</custom>' }),
        };

        wrapper.vm.detectCurrentTemplate();

        expect(wrapper.vm.productComparison.templateName).toBeNull();
    });

    it('should set templateName without modal when selecting a template with unchanged content', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const template = { bodyTemplate: '<item />', headerTemplate: '<?xml ?>' };
        wrapper.vm.productComparison.templates = { google: template };
        wrapper.vm.salesChannel.productExports = {
            first: () => ({ bodyTemplate: '<item />', headerTemplate: '<?xml ?>' }),
        };

        wrapper.vm.onTemplateSelected('google');

        expect(wrapper.vm.productComparison.templateName).toBe('google');
        expect(wrapper.vm.productComparison.showTemplateModal).toBe(false);
    });

    it('should store previousTemplateName and show modal when template content differs', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.productComparison.templateName = 'google';
        wrapper.vm.productComparison.templates = {
            idealo: { name: 'idealo', bodyTemplate: '"sku"|"title"' },
        };
        wrapper.vm.salesChannel.productExports = {
            first: () => ({ bodyTemplate: '<item />' }),
        };

        wrapper.vm.onTemplateSelected('idealo');

        expect(wrapper.vm.productComparison.previousTemplateName).toBe('google');
        expect(wrapper.vm.productComparison.templateName).toBe('idealo');
        expect(wrapper.vm.productComparison.showTemplateModal).toBe(true);
    });

    it('should restore previousTemplateName when template modal is closed', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.productComparison.templateName = 'idealo';
        wrapper.vm.productComparison.previousTemplateName = 'google';
        wrapper.vm.productComparison.showTemplateModal = true;
        wrapper.vm.productComparison.selectedTemplate = { bodyTemplate: '"sku"' };

        wrapper.vm.onTemplateModalClose();

        expect(wrapper.vm.productComparison.templateName).toBe('google');
        expect(wrapper.vm.productComparison.previousTemplateName).toBeNull();
        expect(wrapper.vm.productComparison.selectedTemplate).toBeNull();
        expect(wrapper.vm.productComparison.showTemplateModal).toBe(false);
    });

    it('should apply template with providerName mapping on modal confirm', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.productComparison.previousTemplateName = 'google';
        wrapper.vm.productComparison.templateName = 'open_ai';
        wrapper.vm.productComparison.selectedTemplate = {
            bodyTemplate: '{{ feedRow }}',
            headerTemplate: '',
            footerTemplate: '',
            providerName: 'open-ai',
        };
        wrapper.vm.productComparison.showTemplateModal = true;

        const productExport = wrapper.vm.productExport;

        wrapper.vm.onTemplateModalConfirm();

        expect(productExport.bodyTemplate).toBe('{{ feedRow }}');
        expect(productExport.provider).toBe('open-ai');
        expect(wrapper.vm.productComparison.templateName).toBe('open_ai');
        expect(wrapper.vm.productComparison.previousTemplateName).toBeNull();
        expect(wrapper.vm.productComparison.showTemplateModal).toBe(false);
    });

    it('should return true when required agentic commerce fields have values', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({
            agenticCommerceExportConfig: [
                {
                    provider: 'open-ai',
                    elements: [
                        {
                            name: 'core.openAiProductExport.returnPolicyUrl',
                            config: { required: true },
                        },
                    ],
                    values: { 'core.openAiProductExport.returnPolicyUrl': 'https://example.com/returns' },
                    errors: {},
                    isLoaded: true,
                    isLoading: false,
                },
            ],
        });

        expect(wrapper.vm.validateAgenticCommerceExportConfig()).toBe(true);
        expect(wrapper.vm.agenticCommerceExportConfig[0].errors).toEqual({});
    });

    it('should return false and sets field error when a required agentic commerce field is empty', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({
            agenticCommerceExportConfig: [
                {
                    provider: 'open-ai',
                    elements: [
                        {
                            name: 'core.openAiProductExport.returnPolicyUrl',
                            config: { required: true },
                        },
                    ],
                    values: {},
                    errors: {},
                    isLoaded: true,
                    isLoading: false,
                },
            ],
        });

        const result = wrapper.vm.validateAgenticCommerceExportConfig();

        expect(result).toBe(false);
        expect(wrapper.vm.agenticCommerceExportConfig[0].errors['core.openAiProductExport.returnPolicyUrl']).toBeDefined();
        expect(wrapper.vm.agenticCommerceExportConfig[0].errors['core.openAiProductExport.returnPolicyUrl'].code).toBe(
            'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
        );
    });

    it('should not call save when a required agentic commerce field is empty', async () => {
        const wrapper = await createWrapper({
            salesChannelResponse: {
                typeId: Shopware.Defaults.agenticCommerceTypeId,
            },
        });

        await flushPromises();
        mockSave.mockClear();

        await wrapper.setData({
            isLoading: true,
            agenticCommerceExportConfig: [
                {
                    provider: 'open-ai',
                    elements: [
                        {
                            name: 'core.openAiProductExport.returnPolicyUrl',
                            config: { required: true },
                        },
                    ],
                    values: {},
                    errors: {},
                    isLoaded: true,
                    isLoading: false,
                },
            ],
        });

        await wrapper.vm.onSave();
        await flushPromises();

        expect(mockSave).not.toHaveBeenCalled();
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should ignore required fields of inactive providers when validating agentic commerce config', async () => {
        const wrapper = await createWrapper({
            salesChannelResponse: {
                typeId: Shopware.Defaults.agenticCommerceTypeId,
                productExports: {
                    first: () => ({ provider: 'google' }),
                },
            },
        });
        await flushPromises();

        await wrapper.setData({
            agenticCommerceExportConfig: [
                {
                    provider: 'open-ai',
                    elements: [
                        {
                            name: 'core.openAiProductExport.returnPolicyUrl',
                            config: { required: true },
                        },
                    ],
                    values: {},
                    errors: {},
                    isLoaded: true,
                    isLoading: false,
                },
                {
                    provider: 'google',
                    elements: [],
                    values: {},
                    errors: {},
                    isLoaded: true,
                    isLoading: false,
                },
            ],
        });

        expect(wrapper.vm.productExport.provider).toBe('google');
        expect(wrapper.vm.validateAgenticCommerceExportConfig()).toBe(true);
        expect(wrapper.vm.agenticCommerceExportConfig[0].errors).toEqual({});
    });
});
