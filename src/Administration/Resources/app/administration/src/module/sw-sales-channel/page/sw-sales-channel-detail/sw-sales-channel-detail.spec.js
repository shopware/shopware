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

const mockTemplates = {
    'google-product-search-de': {
        name: 'google-product-search-de',
        bodyTemplate: '<item><g:id>{{ product.productNumber }}</g:id></item>',
        headerTemplate: '<?xml version="1.0" encoding="UTF-8" ?>',
        footerTemplate: '</channel></rss>',
        fileName: 'google.xml',
        encoding: 'UTF-8',
        fileFormat: 'xml',
    },
    'idealo': {
        name: 'idealo',
        bodyTemplate: '"{{ product.productNumber }}"|"{{ product.translated.name }}"',
        headerTemplate: '"sku"|"title"',
        footerTemplate: '',
        fileName: 'idealo.csv',
        encoding: 'UTF-8',
        fileFormat: 'csv',
    },
    'open_ai': {
        name: 'open_ai',
        bodyTemplate: '{{ feedRow|json_encode(constant("JSON_UNESCAPED_SLASHES"))|raw }}',
        headerTemplate: '',
        footerTemplate: '',
        providerName: 'open-ai',
        encoding: 'UTF-8',
        fileFormat: 'jsonl',
    },
};

async function createWrapper() {
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
                    getConfig: () => Promise.resolve([]),
                    getValues: () => Promise.resolve({}),
                    batchSave: () => Promise.resolve(),
                },
            },
            mocks: {
                $route: {
                    params: {
                        id: '1a2b3c4d',
                    },
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

    it('should detect open_ai agentic commerce template on load when product export bodyTemplate matches', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.salesChannel.productExports = {
            first: () => ({ bodyTemplate: mockTemplates.open_ai.bodyTemplate }),
        };

        wrapper.vm.detectCurrentTemplate();

        expect(wrapper.vm.productComparison.templateName).toBe('open_ai');
    });

    it('should not detect a template when product export bodyTemplate does not match any registered template', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.salesChannel.productExports = {
            first: () => ({ bodyTemplate: '<custom>{{ product.name }}</custom>' }),
        };

        wrapper.vm.detectCurrentTemplate();

        expect(wrapper.vm.productComparison.templateName).toBeNull();
    });

    it('should set templateName without modal when selecting a product comparison template with unchanged content', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const google = mockTemplates['google-product-search-de'];
        wrapper.vm.salesChannel.productExports = {
            first: () => ({
                bodyTemplate: google.bodyTemplate,
                headerTemplate: google.headerTemplate,
                footerTemplate: google.footerTemplate,
                fileName: google.fileName,
                encoding: google.encoding,
                fileFormat: google.fileFormat,
                name: google.name,
            }),
        };

        wrapper.vm.onTemplateSelected('google-product-search-de');

        expect(wrapper.vm.productComparison.templateName).toBe('google-product-search-de');
        expect(wrapper.vm.productComparison.showTemplateModal).toBe(false);
    });

    it('should store previousTemplateName and show modal when switching from google to idealo template', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.productComparison.templateName = 'google-product-search-de';

        const { bodyTemplate, headerTemplate, footerTemplate } = mockTemplates['google-product-search-de'];
        wrapper.vm.salesChannel.productExports = {
            first: () => ({ bodyTemplate, headerTemplate, footerTemplate }),
        };

        wrapper.vm.onTemplateSelected('idealo');

        expect(wrapper.vm.productComparison.previousTemplateName).toBe('google-product-search-de');
        expect(wrapper.vm.productComparison.templateName).toBe('idealo');
        expect(wrapper.vm.productComparison.showTemplateModal).toBe(true);
    });

    it('should restore previousTemplateName when template modal is closed without confirming', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.productComparison.templateName = 'idealo';
        wrapper.vm.productComparison.previousTemplateName = 'google-product-search-de';
        wrapper.vm.productComparison.showTemplateModal = true;
        wrapper.vm.productComparison.selectedTemplate = { ...mockTemplates.idealo };

        wrapper.vm.onTemplateModalClose();

        expect(wrapper.vm.productComparison.templateName).toBe('google-product-search-de');
        expect(wrapper.vm.productComparison.previousTemplateName).toBeNull();
        expect(wrapper.vm.productComparison.selectedTemplate).toBeNull();
        expect(wrapper.vm.productComparison.showTemplateModal).toBe(false);
    });

    it('should apply open_ai agentic commerce template with providerName mapping and keep templateName on confirm', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.productComparison.previousTemplateName = 'google-product-search-de';
        wrapper.vm.productComparison.templateName = 'open_ai';
        wrapper.vm.productComparison.selectedTemplate = { ...mockTemplates.open_ai };
        wrapper.vm.productComparison.showTemplateModal = true;

        const productExport = wrapper.vm.productExport;

        wrapper.vm.onTemplateModalConfirm();

        expect(productExport.bodyTemplate).toBe(mockTemplates.open_ai.bodyTemplate);
        expect(productExport.headerTemplate).toBe('');
        expect(productExport.footerTemplate).toBe('');
        expect(productExport.provider).toBe('open-ai');
        expect(wrapper.vm.productComparison.templateName).toBe('open_ai');
        expect(wrapper.vm.productComparison.previousTemplateName).toBeNull();
        expect(wrapper.vm.productComparison.showTemplateModal).toBe(false);
    });
});
