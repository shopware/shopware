import { mount } from '@vue/test-utils';
import { createPinia } from 'pinia';

/**
 * @sw-package after-sales
 */

const documentTypeMock = [
    {
        technicalName: 'invoice',
        translated: { name: 'Invoice' },
        id: '1',
    },
    {
        technicalName: 'credit_note',
        translated: { name: 'Credit note' },
        id: '2',
    },
    {
        technicalName: 'storno',
        translated: { name: 'Cancellation invoice' },
        id: '3',
    },
    {
        technicalName: 'delivery_note',
        translated: { name: 'Delivery note' },
        id: '4',
    },
];

const supportedDocumentTypesMock = {
    invoice: {
        formats: [
            'pdf',
            'zugferd_xml',
        ],
    },
    credit_note: { formats: ['pdf'] },
};

async function createWrapper(sequence = {}) {
    return mount(
        await wrapTestComponent('sw-flow-generate-document-modal', {
            sync: true,
        }),
        {
            global: {
                plugins: [createPinia()],
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve(documentTypeMock),
                            };
                        },
                    },
                    documentV2Service: {
                        getFileFormatSnippet: (format) => `sw-order.components.createDocumentModal.fileFormats.${format}`,
                        getDocumentTypeLabel: (technicalName) =>
                            `sw-order.components.createDocumentModal.documentTypes.${technicalName}`,
                        getAvailableDocumentTypes: () => Promise.resolve(supportedDocumentTypesMock),
                    },
                },
                data() {
                    return {
                        documentTypesSelected: [],
                        documentTypeSelected: null,
                        fileFormatsSelected: [],
                        supportedDocumentTypes: {},
                        fieldError: null,
                        fileFormatsFieldError: null,
                    };
                },
                stubs: {
                    'sw-modal': {
                        template: `
                    <div class="sw-modal">
                      <slot name="modal-header"></slot>
                      <slot></slot>
                      <slot name="modal-footer"></slot>
                    </div>
                `,
                    },
                    'sw-multi-select': await wrapTestComponent('sw-multi-select'),
                    'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                    'sw-select-result': await wrapTestComponent('sw-select-result'),
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                    'sw-popover': await wrapTestComponent('sw-popover'),
                    'sw-highlight-text': true,
                    'sw-label': true,
                    'sw-field-error': true,
                    'sw-loader': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                },
            },
            props: {
                sequence,
            },
        },
    );
}

describe('module/sw-flow/component/sw-flow-generate-document-modal', () => {
    // Legacy document generation remains supported while DOCUMENT_GENERATION_REWORK is toggleable.
    it.inactiveFeatureFlags(['DOCUMENT_GENERATION_REWORK'])(
        'should show validation if document multiple type field is empty',
        async () => {
            const wrapper = await createWrapper();

            const saveButton = wrapper.find('.sw-flow-generate-document-modal__save-button');
            await saveButton.trigger('click');
            await flushPromises();

            const documentTypeSelect = wrapper.find('.sw-flow-generate-document-modal__type-multi-select');
            expect(documentTypeSelect.classes()).toContain('has--error');

            await wrapper.setData({
                documentTypesSelected: ['invoice'],
            });

            await saveButton.trigger('click');

            expect(documentTypeSelect.classes()).not.toContain('has--error');
        },
    );

    // Legacy document generation remains supported while DOCUMENT_GENERATION_REWORK is toggleable.
    it.inactiveFeatureFlags(['DOCUMENT_GENERATION_REWORK'])(
        'should emit process-finish when document multiple type is selected',
        async () => {
            const wrapper = await createWrapper();
            await wrapper.setData({
                documentTypesSelected: [
                    'invoice',
                    'delivery_note',
                ],
            });

            const saveButton = wrapper.find('.sw-flow-generate-document-modal__save-button');
            await saveButton.trigger('click');
            await flushPromises();

            expect(wrapper.emitted()['process-finish'][0]).toEqual([
                {
                    config: {
                        documentTypes: [
                            {
                                documentType: 'invoice',
                                documentRangerType: 'document_invoice',
                            },
                            {
                                documentType: 'delivery_note',
                                documentRangerType: 'document_delivery_note',
                            },
                        ],
                    },
                },
            ]);
        },
    );

    // Legacy document generation remains supported while DOCUMENT_GENERATION_REWORK is toggleable.
    it.inactiveFeatureFlags(['DOCUMENT_GENERATION_REWORK'])(
        'should not preselect a document type when switching back from a v2 config and require an explicit choice before saving',
        async () => {
            const wrapper = await createWrapper({
                config: {
                    documentType: 'invoice',
                    fileFormats: ['pdf'],
                },
            });

            expect(wrapper.vm.documentTypesSelected).toEqual([]);

            const saveButton = wrapper.find('.sw-flow-generate-document-modal__save-button');
            await saveButton.trigger('click');
            await flushPromises();

            expect(wrapper.emitted()['process-finish']).toBeUndefined();

            const documentTypeSelect = wrapper.find('.sw-flow-generate-document-modal__type-multi-select');
            expect(documentTypeSelect.classes()).toContain('has--error');
        },
    );

    describe('document generation rework', () => {
        afterEach(() => {
            jest.restoreAllMocks();
        });

        it('should show the legacy multi-select and hide the single type and file formats selects when the feature is inactive', async () => {
            jest.spyOn(Shopware.Feature, 'isActive').mockImplementation((flag) => flag !== 'DOCUMENT_GENERATION_REWORK');

            const wrapper = await createWrapper();

            expect(wrapper.find('.sw-flow-generate-document-modal__type-multi-select').exists()).toBe(true);
            expect(wrapper.find('.sw-flow-generate-document-modal__type-select').exists()).toBe(false);
            expect(wrapper.find('.sw-flow-generate-document-modal__file-formats-select').exists()).toBe(false);
        });

        it('should load the available types and formats from the v2 route and show the single type and file formats selects when the feature is active', async () => {
            jest.spyOn(Shopware.Feature, 'isActive').mockImplementation((flag) => flag === 'DOCUMENT_GENERATION_REWORK');

            const wrapper = await createWrapper();
            await flushPromises();

            expect(wrapper.find('.sw-flow-generate-document-modal__type-multi-select').exists()).toBe(false);
            expect(wrapper.find('.sw-flow-generate-document-modal__type-select').exists()).toBe(true);
            expect(wrapper.find('.sw-flow-generate-document-modal__file-formats-select').exists()).toBe(true);

            expect(wrapper.vm.supportedDocumentTypes).toEqual(supportedDocumentTypesMock);
            expect(wrapper.vm.documentTypeOptions.map((type) => type.value)).toEqual([
                'invoice',
                'credit_note',
            ]);
        });

        it('should not preselect a document type when the sequence has a legacy multi-type config', async () => {
            jest.spyOn(Shopware.Feature, 'isActive').mockImplementation((flag) => flag === 'DOCUMENT_GENERATION_REWORK');

            const wrapper = await createWrapper({
                config: {
                    documentTypes: [
                        { documentType: 'invoice' },
                        { documentType: 'credit_note' },
                    ],
                },
            });
            await flushPromises();

            expect(wrapper.vm.documentTypeSelected).toBeNull();
        });

        it('should show an error notification and keep the previously loaded types when reloading the available types fails', async () => {
            jest.spyOn(Shopware.Feature, 'isActive').mockImplementation((flag) => flag === 'DOCUMENT_GENERATION_REWORK');

            const wrapper = await createWrapper();
            await flushPromises();

            expect(wrapper.vm.supportedDocumentTypes).toEqual(supportedDocumentTypesMock);

            wrapper.vm.createNotificationError = jest.fn();
            wrapper.vm.documentV2Service.getAvailableDocumentTypes = jest.fn(() =>
                Promise.reject(new Error('Network error')),
            );

            await wrapper.vm.loadSupportedDocumentTypes();

            expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
                message: 'Network error',
            });
            expect(wrapper.vm.isLoadingSupportedDocumentTypes).toBe(false);
            expect(wrapper.vm.supportedDocumentTypes).toEqual(supportedDocumentTypesMock);
        });

        it('should reset the selected file formats when the document type changes', async () => {
            jest.spyOn(Shopware.Feature, 'isActive').mockImplementation((flag) => flag === 'DOCUMENT_GENERATION_REWORK');

            const wrapper = await createWrapper();
            await flushPromises();

            await wrapper.setData({
                documentTypeSelected: 'invoice',
                fileFormatsSelected: ['pdf'],
            });

            expect(wrapper.vm.fileFormatOptions.map((format) => format.value)).toEqual([
                'pdf',
                'zugferd_xml',
            ]);

            wrapper.vm.onDocumentTypeSelectedChange('credit_note');
            await flushPromises();

            expect(wrapper.vm.documentTypeSelected).toBe('credit_note');
            expect(wrapper.vm.fileFormatsSelected).toEqual([]);
            expect(wrapper.vm.fileFormatOptions.map((format) => format.value)).toEqual(['pdf']);
        });

        it('should show validation errors when saving without a document type or file formats selected', async () => {
            jest.spyOn(Shopware.Feature, 'isActive').mockImplementation((flag) => flag === 'DOCUMENT_GENERATION_REWORK');

            const wrapper = await createWrapper();
            await flushPromises();

            const saveButton = wrapper.find('.sw-flow-generate-document-modal__save-button');
            await saveButton.trigger('click');
            await flushPromises();

            expect(wrapper.find('.sw-flow-generate-document-modal__type-select').classes()).toContain('has--error');
            expect(wrapper.find('.sw-flow-generate-document-modal__file-formats-select').classes()).toContain('has--error');

            expect(wrapper.emitted()['process-finish']).toBeUndefined();
        });

        it('should emit process-finish with the single document type and selected file formats', async () => {
            jest.spyOn(Shopware.Feature, 'isActive').mockImplementation((flag) => flag === 'DOCUMENT_GENERATION_REWORK');

            const wrapper = await createWrapper();
            await flushPromises();

            await wrapper.setData({
                documentTypeSelected: 'invoice',
                fileFormatsSelected: [
                    'pdf',
                    'zugferd_xml',
                ],
            });

            const saveButton = wrapper.find('.sw-flow-generate-document-modal__save-button');
            await saveButton.trigger('click');
            await flushPromises();

            expect(wrapper.emitted()['process-finish'][0]).toEqual([
                {
                    config: {
                        documentType: 'invoice',
                        fileFormats: [
                            'pdf',
                            'zugferd_xml',
                        ],
                    },
                },
            ]);
        });
    });
});
