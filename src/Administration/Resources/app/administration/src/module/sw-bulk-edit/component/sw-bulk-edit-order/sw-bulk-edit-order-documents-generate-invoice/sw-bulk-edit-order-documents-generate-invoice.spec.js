/**
 * @sw-package checkout
 */
import { mount } from '@vue/test-utils';

async function createWrapper(documentV2ServiceOverrides = {}) {
    return mount(await wrapTestComponent('sw-bulk-edit-order-documents-generate-invoice', { sync: true }), {
        global: {
            stubs: {
                'sw-datepicker': true,
                'sw-textarea-field': true,
                'mt-select': true,
            },
            provide: {
                documentV2Service: {
                    sortFileFormats: (formats) => {
                        const priority = [
                            'pdf',
                            'html',
                            'zugferd_embedded_pdf',
                            'zugferd_xml',
                        ];

                        return [...formats].sort((left, right) => priority.indexOf(left) - priority.indexOf(right));
                    },
                    getFileFormatSnippet: (format) => `sw-order.components.createDocumentModal.fileFormats.${format}`,
                    getAvailableDocumentTypes: jest.fn().mockResolvedValue({
                        invoice: {
                            formats: [
                                'zugferd_xml',
                                'pdf',
                                'html',
                            ],
                        },
                    }),
                    ...documentV2ServiceOverrides,
                },
            },
        },
    });
}

describe('sw-bulk-edit-order-documents-generate-invoice', () => {
    let wrapper;

    beforeEach(async () => {
        global.activeFeatureFlags = [];
        wrapper = await createWrapper();
    });

    it('should contain a generateData as a computed property', async () => {
        expect(wrapper.vm.generateData).toEqual(
            expect.objectContaining({
                documentComment: null,
            }),
        );

        Shopware.Store.get('swBulkEdit').setOrderDocumentsValue({
            type: 'invoice',
            value: {
                documentDate: 'documentDate',
                documentComment: 'documentComment',
            },
        });

        expect(wrapper.vm.generateData).toEqual(
            expect.objectContaining({
                documentDate: 'documentDate',
                documentComment: 'documentComment',
            }),
        );
    });

    it('should be able to update generateData', async () => {
        wrapper.vm.generateData = {
            documentDate: 'I am a date',
            documentComment: 'I am a comment',
        };

        expect(wrapper.vm.generateData.documentDate).toBe('I am a date');
        expect(wrapper.vm.generateData.documentComment).toBe('I am a comment');
    });

    it('should not fetch available document types when the feature flag is inactive', async () => {
        expect(wrapper.vm.supportedDocumentTypes).toEqual({});
        expect(wrapper.vm.fileFormatOptions).toEqual([]);
    });

    it('should fetch and sort file format options when the feature flag is active', async () => {
        global.activeFeatureFlags = ['DOCUMENT_GENERATION_REWORK'];
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.fileFormatOptions).toEqual([
            { label: 'sw-order.components.createDocumentModal.fileFormats.pdf', value: 'pdf' },
            { label: 'sw-order.components.createDocumentModal.fileFormats.html', value: 'html' },
            { label: 'sw-order.components.createDocumentModal.fileFormats.zugferd_xml', value: 'zugferd_xml' },
        ]);
    });
});
