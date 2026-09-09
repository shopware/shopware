import { config, mount } from '@vue/test-utils';
import ShopwareError from 'src/core/data/ShopwareError';
import { COMPANY_SETTINGS_MOVED_BANNER_STORAGE_KEY } from './index';

/**
 * @sw-package after-sales
 */
const documentBaseConfigRepositoryMock = {
    create: () => {
        return {};
    },
    get: (id) => {
        const salesChannels = new Shopware.Data.EntityCollection('source', 'entity', Shopware.Context.api);

        if (id === 'documentConfigWithSalesChannels') {
            salesChannels.push({
                id: 'associationId1',
                salesChannelId: 'salesChannelId1',
            });

            return Promise.resolve({
                id: id,
                documentTypeId: 'documentTypeId1',
                salesChannels: salesChannels,
            });
        }

        if (id === 'documentConfigWithDocumentType') {
            return Promise.resolve({
                id: id,
                documentTypeId: 'documentTypeId1',
                salesChannels: salesChannels,
                documentType: { id: 'documentTypeId1' },
            });
        }

        if (id === 'documentConfigWithDocumentTypeAndSalesChannels') {
            salesChannels.push({
                id: 'associationId1',
                salesChannelId: 'salesChannelId1',
            });

            return Promise.resolve({
                id: id,
                documentTypeId: 'documentTypeId1',
                salesChannels: salesChannels,
                documentType: { id: 'documentTypeId1' },
            });
        }

        if (id === 'documentConfigWithDocumentFileTypes') {
            return Promise.resolve({
                id: id,
                documentTypeId: 'documentTypeId',
                config: {
                    fileTypes: [
                        'pdf',
                        'html',
                    ],
                },
            });
        }

        if (id === 'documentConfigWithoutDocumentFileTypesArray') {
            return Promise.resolve({
                id: id,
                documentTypeId: 'documentTypeId',
                config: {},
            });
        }

        if (id === 'documentConfigWithFormats') {
            return Promise.resolve({
                id: id,
                documentTypeId: 'documentTypeId1',
                config: {},
                documentType: { id: 'documentTypeId1', technicalName: 'invoice' },
                filenameInfixes: null,
            });
        }

        return Promise.resolve({
            id: id,
            documentTypeId: 'documentTypeId',
            config: {
                fileTypes: [
                    'pdf',
                ],
            },
        });
    },

    save: jest.fn(),
};

const salesChannelRepositoryMock = {
    search: () => {
        return [
            { id: 'salesChannelId1', name: 'salesChannel1' },
            { id: 'salesChannelId2', name: 'salesChannel2' },
        ];
    },
};

const documentBaseConfigSalesChannelsRepositoryMock = {
    counter: 1,
    create: () => {
        const association = {
            id: `configSalesChannelId${documentBaseConfigSalesChannelsRepositoryMock.counter}`,
        };

        documentBaseConfigSalesChannelsRepositoryMock.counter += 1;

        return association;
    },
    search: () => {
        return Promise.resolve([]);
    },
};

const documentV2ServiceMock = {
    getFileFormatSnippet: jest.fn((format) => `sw-order.components.createDocumentModal.fileFormats.${format}`),
    getAvailableDocumentTypes: jest.fn(() =>
        Promise.resolve({
            invoice: {
                formats: [
                    'html',
                    'pdf',
                    'zugferd_xml',
                    'zugferd_embedded_pdf',
                ],
            },
        }),
    ),
};

const repositoryMockFactory = (entity) => {
    if (entity === 'sales_channel') {
        return salesChannelRepositoryMock;
    }

    if (entity === 'document_base_config') {
        return documentBaseConfigRepositoryMock;
    }

    if (entity === 'document_base_config_sales_channel') {
        return documentBaseConfigSalesChannelsRepositoryMock;
    }

    return false;
};

const createWrapper = async (customOptions, privileges = [], isDocumentGenerationReworkActive = false) => {
    return mount(
        await wrapTestComponent('sw-settings-document-detail', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'sw-page': {
                        template: `
                    <div class="sw-page">
                        <slot name="search-bar"></slot>
                        <slot name="smart-bar-back"></slot>
                        <slot name="smart-bar-header"></slot>
                        <slot name="smart-bar-actions"></slot>
                        <slot name="side-content"></slot>
                        <slot name="content"></slot>
                        <slot name="sidebar"></slot>
                        <slot></slot>
                    </div>
                `,
                    },
                    'sw-form-field-renderer': await wrapTestComponent('sw-form-field-renderer'),
                },
                provide: {
                    repositoryFactory: {
                        create: (entity) => repositoryMockFactory(entity),
                    },
                    feature: {
                        isActive: (flag) => flag === 'DOCUMENT_GENERATION_REWORK' && isDocumentGenerationReworkActive,
                    },
                    acl: {
                        can: (key) => (key ? privileges.includes(key) : true),
                    },
                    customFieldDataProviderService: {
                        getCustomFieldSets: () => Promise.resolve([]),
                    },
                    documentV2Service: documentV2ServiceMock,
                },
            },
            ...customOptions,
        },
    );
};

describe('src/module/sw-settings-document/page/sw-settings-document-detail', () => {
    beforeEach(async () => {
        documentBaseConfigSalesChannelsRepositoryMock.counter = 1;
        documentBaseConfigRepositoryMock.save.mockReset();
        documentBaseConfigRepositoryMock.save.mockResolvedValue();
        documentV2ServiceMock.getAvailableDocumentTypes.mockClear();
        localStorage.removeItem(COMPANY_SETTINGS_MOVED_BANNER_STORAGE_KEY);
        Shopware.Store.get('error').resetApiErrors();
    });

    it('should create an array with sales channel ids from the document config sales channels association', async () => {
        const wrapper = await createWrapper({
            props: { documentConfigId: 'documentConfigWithSalesChannels' },
        });
        await flushPromises();

        expect([...wrapper.vm.documentConfigSalesChannels]).toEqual([
            'salesChannelId1',
        ]);
    });

    it('should create an entity collection with document config sales channels associations', async () => {
        const wrapper = await createWrapper({
            props: { documentConfigId: 'documentConfigWithDocumentType' },
        });
        await flushPromises();

        expect(wrapper.vm.documentConfigSalesChannelOptionsCollection[0]).toEqual({
            documentBaseConfigId: 'documentConfigWithDocumentType',
            documentTypeId: 'documentTypeId1',
            id: 'configSalesChannelId1',
            salesChannel: { id: 'salesChannelId1', name: 'salesChannel1' },
            salesChannelId: 'salesChannelId1',
        });
        expect(wrapper.vm.documentConfigSalesChannelOptionsCollection[1]).toEqual({
            documentBaseConfigId: 'documentConfigWithDocumentType',
            documentTypeId: 'documentTypeId1',
            id: 'configSalesChannelId2',
            salesChannel: { id: 'salesChannelId2', name: 'salesChannel2' },
            salesChannelId: 'salesChannelId2',
        });
    });

    it(
        'should create an entity collection with document config sales channels associations with ' +
            'actual sales channels associations inside',
        async () => {
            const wrapper = await createWrapper({
                props: {
                    documentConfigId: 'documentConfigWithDocumentTypeAndSalesChannels',
                },
            });
            await flushPromises();

            expect(wrapper.vm.documentConfigSalesChannelOptionsCollection[0]).toEqual({
                id: 'associationId1',
                salesChannelId: 'salesChannelId1',
            });
            expect(wrapper.vm.documentConfigSalesChannelOptionsCollection[1]).toEqual({
                documentBaseConfigId: 'documentConfigWithDocumentTypeAndSalesChannels',
                documentTypeId: 'documentTypeId1',
                id: 'configSalesChannelId1',
                salesChannel: { id: 'salesChannelId2', name: 'salesChannel2' },
                salesChannelId: 'salesChannelId2',
            });
        },
    );

    it('should recreate sales channel options collection when type changes', async () => {
        const wrapper = await createWrapper({
            props: {
                documentConfigId: 'documentConfigWithDocumentTypeAndSalesChannels',
            },
        });
        await flushPromises();

        expect([...wrapper.vm.documentConfigSalesChannels]).toEqual([
            'salesChannelId1',
        ]);

        wrapper.vm.onChangeType({ id: 'documentTypeId2' });

        expect(wrapper.vm.documentConfigSalesChannelOptionsCollection[0]).toEqual({
            id: 'associationId1',
            salesChannelId: 'salesChannelId1',
        });
        expect(wrapper.vm.documentConfigSalesChannelOptionsCollection[1]).toEqual({
            documentBaseConfigId: 'documentConfigWithDocumentTypeAndSalesChannels',
            documentTypeId: 'documentTypeId2',
            id: 'configSalesChannelId2',
            salesChannel: { id: 'salesChannelId2', name: 'salesChannel2' },
            salesChannelId: 'salesChannelId2',
        });

        expect(wrapper.vm.documentConfigSalesChannels).toEqual([]);
    });

    it('should be able to edit', async () => {
        const wrapper = await createWrapper(
            {
                props: {
                    documentConfigId: 'documentConfigWithDocumentTypeAndSalesChannels',
                },
            },
            ['document.editor'],
        );
        await flushPromises();

        expect(wrapper.find('.sw-settings-document-detail__save-action').attributes().disabled).toBeUndefined();
        expect(wrapper.findAll('.mt-field').every((field) => !field.classes('is--disabled'))).toBe(true);
        expect(wrapper.find('#documentSalesChannel').attributes('disabled')).toBe('false');
    });

    it('should not be able to edit', async () => {
        const wrapper = await createWrapper({
            props: {
                documentConfigId: 'documentConfigWithDocumentTypeAndSalesChannels',
            },
        });
        await flushPromises();

        expect(wrapper.find('.sw-settings-document-detail__save-action').attributes().disabled).toBeDefined();
        expect(wrapper.findAll('.mt-field').every((field) => field.classes('is--disabled'))).toBe(true);
        expect(wrapper.find('#documentSalesChannel').attributes('disabled')).toBe('true');
    });

    it('should create an invoice document with countries note delivery', async () => {
        const wrapper = await createWrapper({}, ['document.editor']);
        await flushPromises();

        await wrapper.setData({
            isShowDisplayNoteDelivery: true,
            documentConfig: {
                config: {
                    displayAdditionalNoteDelivery: true,
                },
            },
        });

        const displayAdditionalNoteDeliveryCheckbox = wrapper.findComponent(
            '.sw-settings-document-detail__field_additional_note_delivery',
        );

        expect(displayAdditionalNoteDeliveryCheckbox.props('checked')).toBe(true);
        expect(displayAdditionalNoteDeliveryCheckbox.props('label')).toBe(
            'sw-settings-document.detail.labelDisplayAdditionalNoteDelivery',
        );
    });

    it('should render the company settings layout with feature flag', async () => {
        const wrapper = await createWrapper(
            {
                props: { documentConfigId: 'documentConfigWithDocumentType' },
            },
            [],
            true,
        );
        await flushPromises();

        expect(wrapper.find('.sw-settings-document-detail__company_card').exists()).toBe(false);
        expect(wrapper.find('.sw-settings-document-detail__company-settings-moved-banner').exists()).toBe(true);
        expect(wrapper.find('.sw-settings-document-detail__field-display-company-address').exists()).toBe(true);
        expect(wrapper.find('.sw-settings-document-detail__field-display-return-address').exists()).toBe(true);
    });

    it('should always include payment due date in the general form fields', async () => {
        const wrapper = await createWrapper({
            props: { documentConfigId: 'documentConfigWithDocumentType' },
        });
        await flushPromises();

        expect(wrapper.vm.generalFormFields.map((field) => field.name)).toContain('paymentDueDate');

        const paymentDueDateField = wrapper.vm.generalFormFields.find((field) => field.name === 'paymentDueDate');
        expect(paymentDueDateField.config.helpText).toBe('sw-settings-document.detail.helpTextPaymentDueDate');
    });

    it('should include fileTypes in the general form fields when DOCUMENT_GENERATION_REWORK is inactive', async () => {
        const wrapper = await createWrapper({
            props: { documentConfigId: 'documentConfigWithDocumentType' },
        });
        await flushPromises();

        expect(wrapper.vm.generalFormFields.map((field) => field.name)).toContain('fileTypes');
    });

    it('should exclude fileTypes from the general form fields when DOCUMENT_GENERATION_REWORK is active', async () => {
        const wrapper = await createWrapper(
            {
                props: { documentConfigId: 'documentConfigWithDocumentType' },
            },
            [],
            true,
        );
        await flushPromises();

        expect(wrapper.vm.generalFormFields.map((field) => field.name)).not.toContain('fileTypes');
    });

    it('should show errors on payment due date field if value is not valid', async () => {
        documentBaseConfigRepositoryMock.save.mockRejectedValueOnce({
            response: {
                data: {
                    errors: [
                        {
                            code: 'DOCUMENT_BASE_CONFIG_INVALID_PAYMENT_DUE_DATE',
                        },
                    ],
                },
            },
        });

        const wrapper = await createWrapper(
            {
                props: { documentConfigId: 'documentConfigWithDocumentType' },
            },
            ['document.editor'],
        );
        await flushPromises();

        expect(
            wrapper
                .findAll('.mt-field')
                .filter((field) => field.find('#paymentDueDate').exists())[0]
                .find('.mt-field__error')
                .exists(),
        ).toBe(false);

        await wrapper.get('.sw-settings-document-detail__save-action').trigger('click');
        await flushPromises();

        expect(documentBaseConfigRepositoryMock.save).toHaveBeenCalledTimes(1);
        expect(
            wrapper
                .findAll('.mt-field')
                .filter((field) => field.find('#paymentDueDate').exists())[0]
                .find('.mt-field__error')
                .text(),
        ).toBe('sw-settings-document.errors.invalidDueDateFormat');
    });

    it('should render the company settings layout without feature flag', async () => {
        const wrapper = await createWrapper({
            props: { documentConfigId: 'documentConfigWithDocumentType' },
        });
        await flushPromises();

        expect(wrapper.find('.sw-settings-document-detail__field-display-company-address').exists()).toBe(false);
        expect(wrapper.find('.sw-settings-document-detail__field-display-return-address').exists()).toBe(false);
        expect(wrapper.vm.generalFormFields.map((field) => field.name)).toContain('paymentDueDate');
        expect(wrapper.find('.sw-settings-document-detail__company_card_display_company').exists()).toBe(true);
        expect(wrapper.find('.sw-settings-document-detail__company_card_display_return').exists()).toBe(true);
    });

    it('should hide the moved company settings banner after closing it', async () => {
        const wrapper = await createWrapper(
            {
                props: { documentConfigId: 'documentConfigWithDocumentType' },
            },
            [],
            true,
        );
        await flushPromises();

        await wrapper.find('.sw-settings-document-detail__company-settings-moved-banner > button').trigger('click');

        expect(wrapper.find('.sw-settings-document-detail__company-settings-moved-banner').exists()).toBe(false);
        expect(localStorage.getItem(COMPANY_SETTINGS_MOVED_BANNER_STORAGE_KEY)).toBe('true');
    });

    it('should keep the moved company settings banner hidden after remounting', async () => {
        const wrapper = await createWrapper(
            {
                props: { documentConfigId: 'documentConfigWithDocumentType' },
            },
            [],
            true,
        );
        await flushPromises();

        await wrapper.find('.sw-settings-document-detail__company-settings-moved-banner > button').trigger('click');

        const remountedWrapper = await createWrapper(
            {
                props: { documentConfigId: 'documentConfigWithDocumentType' },
            },
            [],
            true,
        );
        await flushPromises();

        expect(remountedWrapper.find('.sw-settings-document-detail__company-settings-moved-banner').exists()).toBe(false);
    });

    it('should contain field "display divergent delivery address" in invoice form field', async () => {
        const wrapper = await createWrapper({}, ['document.editor']);

        await wrapper.setData({
            isShowDivergentDeliveryAddress: true,
        });
        await flushPromises();

        const displayDivergentDeliveryAddress = wrapper.findComponent(
            '.sw-settings-document-detail__field_divergent_delivery_address',
        );
        expect(displayDivergentDeliveryAddress).toBeDefined();
        expect(displayDivergentDeliveryAddress.props('label')).toBe(
            'sw-settings-document.detail.labelDisplayDivergentDeliveryAddress',
        );
    });

    it('should not exist "display divergent delivery address" in general form field and company form field', async () => {
        const wrapper = await createWrapper({}, ['document.editor']);
        await flushPromises();

        const companyFormFields = wrapper.vm.companyFormFields;
        const generalFormFields = wrapper.vm.generalFormFields;

        const fieldDivergentDeliveryAddressInCompany = companyFormFields.find(
            (companyFormField) => companyFormField && companyFormField.name === 'displayDivergentDeliveryAddress',
        );
        const fieldDivergentDeliveryAddressInGeneral = generalFormFields.find(
            (generalFormField) => generalFormField && generalFormField.name === 'displayDivergentDeliveryAddress',
        );
        expect(fieldDivergentDeliveryAddressInCompany).toBeUndefined();
        expect(fieldDivergentDeliveryAddressInGeneral).toBeUndefined();
    });

    it('should be have config company phone number', async () => {
        const wrapper = await createWrapper({}, ['document.editor']);
        await flushPromises();

        const companyFormFields = wrapper.vm.companyFormFields;

        expect(companyFormFields.map((item) => item && item.name)).toContain('companyPhone');

        const fieldCompanyPhone = companyFormFields.find((item) => item && item.name === 'companyPhone');
        expect(fieldCompanyPhone).toBeDefined();
        expect(fieldCompanyPhone).toEqual(
            expect.objectContaining({
                name: 'companyPhone',
                type: 'text',
                config: expect.objectContaining({
                    type: 'text',
                    label: expect.any(String),
                }),
            }),
        );
    });

    it('should have assignment card at the top of the page', async () => {
        const wrapper = await createWrapper(
            {
                props: {
                    documentConfigId: 'documentConfigWithDocumentTypeAndSalesChannels',
                },
            },
            ['document.editor'],
        );
        await flushPromises();

        const swCardComponents = wrapper.findAll('.mt-card');

        expect(swCardComponents.length).toBeGreaterThan(0);
        expect(swCardComponents.at(0).attributes()['position-identifier']).toBe('sw-settings-document-detail-assignment');
    });

    it('should be have config file formats only show pdf', async () => {
        const wrapper = await createWrapper(
            {
                props: { documentConfigId: 'documentId' },
            },
            ['document.editor'],
        );
        await flushPromises();

        let multiSelect = wrapper.find('.sw-settings-document-detail__multi-select');

        expect(multiSelect).toBeTruthy();
        expect(multiSelect.attributes().value).toBe('pdf');

        await wrapper.vm.onRemoveDocumentType({ id: 'pdf' });

        multiSelect = wrapper.find('.sw-settings-document-detail__multi-select');

        expect(multiSelect).toBeTruthy();
        expect(multiSelect.attributes().value).toBe('pdf');
    });

    it('should be have config file formats with pdf and html', async () => {
        const wrapper = await createWrapper(
            {
                props: { documentConfigId: 'documentConfigWithDocumentFileTypes' },
            },
            ['document.editor'],
        );
        await flushPromises();

        let multiSelect = wrapper.find('.sw-settings-document-detail__multi-select');

        expect(multiSelect).toBeTruthy();
        expect(multiSelect.attributes().value).toBe('pdf,html');

        await wrapper.vm.onRemoveDocumentType({ id: 'html' });

        multiSelect = wrapper.find('.sw-settings-document-detail__multi-select');

        expect(multiSelect).toBeTruthy();
        expect(multiSelect.attributes().value).toBe('pdf');

        await wrapper.vm.onAddDocumentType({ id: 'html' });

        multiSelect = wrapper.find('.sw-settings-document-detail__multi-select');

        expect(multiSelect).toBeTruthy();
        expect(multiSelect.attributes().value).toBe('pdf,html');
    });

    it('should be possible to select fileTypes without fileTypes property in config', async () => {
        const wrapper = await createWrapper(
            {
                props: { documentConfigId: 'documentConfigWithoutDocumentFileTypesArray' },
            },
            ['document.editor'],
        );
        await flushPromises();

        const multiSelect = wrapper.find('.sw-settings-document-detail__multi-select');

        expect(multiSelect).toBeTruthy();
        expect(multiSelect.attributes().value).toBe('pdf,html');

        await wrapper.vm.onRemoveDocumentType({ id: 'html' });
        expect(multiSelect.attributes().value).toBe('pdf');
    });

    it('should exclude zugferd and app-provided document types from documentCriteria', async () => {
        const wrapper = await createWrapper({}, ['document.editor']);
        await flushPromises();

        expect(wrapper.vm.documentCriteria.filters).toContainEqual({
            type: 'not',
            operator: 'OR',
            queries: [
                {
                    type: 'prefix',
                    field: 'technicalName',
                    value: 'zugferd_',
                },
                {
                    type: 'equals',
                    field: 'technicalName',
                    value: 'app_provided',
                },
            ],
        });
    });

    it.each([
        { name: 'no company form', config: { displayCompanyAddress: false, displayReturnAddress: false } },
        { name: 'return address active', config: { displayCompanyAddress: false, displayReturnAddress: true } },
        { name: 'company address active', config: { displayCompanyAddress: true, displayReturnAddress: false } },
        { name: 'both addresses active', config: { displayCompanyAddress: true, displayReturnAddress: true } },
    ])('should display company settings if company address is selected', async ({ config }) => {
        const wrapper = await createWrapper({}, ['document.editor']);
        await flushPromises();

        expect(wrapper.find('.sw-settings-document-detail__company_card_form').exists()).toBe(false);

        await wrapper.setData({
            documentConfig: {
                config,
            },
        });

        expect(wrapper.find('.sw-settings-document-detail__company_card_form').exists()).toBe(
            config.displayCompanyAddress || config.displayReturnAddress,
        );
    });

    it('should render the filename settings card with the prefix and suffix fields', async () => {
        const wrapper = await createWrapper({
            props: { documentConfigId: 'documentConfigWithDocumentType' },
        });
        await flushPromises();

        const filenameCard = wrapper.find('.sw-settings-document-detail__filename_card');

        expect(filenameCard.exists()).toBe(true);
        expect(filenameCard.attributes()['position-identifier']).toBe('sw-settings-document-detail-filename');
        expect(wrapper.find('.sw-settings-document-detail__field_file_name_prefix').exists()).toBe(true);
        expect(wrapper.find('.sw-settings-document-detail__field_file_name_suffix').exists()).toBe(true);
    });

    it('should not render filename infix fields when DOCUMENT_GENERATION_REWORK is inactive', async () => {
        const wrapper = await createWrapper({
            props: { documentConfigId: 'documentConfigWithFormats' },
        });
        await flushPromises();

        expect(wrapper.find('.sw-settings-document-detail__field_file_name_infix').exists()).toBe(false);
        expect(documentV2ServiceMock.getAvailableDocumentTypes).not.toHaveBeenCalled();
    });

    it('should render a filename infix field per supported format when DOCUMENT_GENERATION_REWORK is active', async () => {
        const wrapper = await createWrapper(
            {
                props: { documentConfigId: 'documentConfigWithFormats' },
            },
            [],
            true,
        );
        await flushPromises();

        expect(documentV2ServiceMock.getAvailableDocumentTypes).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.supportedFormats).toEqual([
            'html',
            'pdf',
            'zugferd_xml',
            'zugferd_embedded_pdf',
        ]);

        const infixFields = wrapper.findAll('.sw-settings-document-detail__field_file_name_infix');

        expect(infixFields).toHaveLength(4);

        expect(wrapper.find('.sw-settings-document-detail__filename_pattern').text()).toContain(
            'sw-settings-document.detail.filenamePattern',
        );

        const infixHeadline = wrapper.find('.sw-settings-document-detail__filename_infix_headline');

        expect(infixHeadline.text()).toContain('sw-settings-document.detail.filenameInfixHeadline');
        expect(infixHeadline.find('sw-help-text').exists()).toBe(true);
    });

    it('should not render the filename infix headline or fields for a new document without a selected document type', async () => {
        const wrapper = await createWrapper({}, [], true);
        await flushPromises();

        expect(wrapper.vm.supportedFormats).toEqual([]);
        expect(wrapper.find('.sw-settings-document-detail__filename_infix_headline').exists()).toBe(false);
        expect(wrapper.find('.sw-settings-document-detail__field_file_name_infix').exists()).toBe(false);
    });

    it('should not render the filename pattern or infix headline when DOCUMENT_GENERATION_REWORK is inactive', async () => {
        const wrapper = await createWrapper({
            props: { documentConfigId: 'documentConfigWithFormats' },
        });
        await flushPromises();

        expect(wrapper.find('.sw-settings-document-detail__filename_pattern').exists()).toBe(false);
        expect(wrapper.find('.sw-settings-document-detail__filename_infix_headline').exists()).toBe(false);
    });

    it('should set filenameInfixes on save to null when no infixes were configured', async () => {
        let savedConfig;
        documentBaseConfigRepositoryMock.save.mockImplementationOnce((config) => {
            savedConfig = JSON.parse(JSON.stringify(config));

            return Promise.resolve();
        });

        const wrapper = await createWrapper({}, ['document.editor'], true);
        await flushPromises();

        await wrapper.find('.sw-settings-document-detail__save-action').trigger('click');
        await flushPromises();

        expect(savedConfig).toEqual({
            config: {
                displayAdditionalNoteDelivery: false,
                displayCompanyAddress: false,
                displayCustomerVatId: false,
                displayDivergentDeliveryAddress: false,
                displayFooter: true,
                displayHeader: true,
                displayLineItemPosition: false,
                displayLineItems: true,
                displayPageCount: true,
                displayPrices: true,
                displayReturnAddress: false,
                fileTypes: [
                    'pdf',
                    'html',
                ],
                itemsPerPage: 10,
                pageOrientation: 'portrait',
                pageSize: 'a4',
            },
            filenameInfixes: null,
            global: false,
            salesChannels: [],
        });
    });

    it('should save filename infixes with the configured values', async () => {
        const wrapper = await createWrapper(
            {
                props: { documentConfigId: 'documentConfigWithFormats' },
            },
            ['document.editor'],
            true,
        );
        await flushPromises();

        await wrapper.find('#sw-field--documentConfig-filenameInfixes-html').setValue('htmlInfix');

        await wrapper.find('.sw-settings-document-detail__save-action').trigger('click');
        await flushPromises();

        expect(documentBaseConfigRepositoryMock.save).toHaveBeenCalledWith({
            config: {
                displayAdditionalNoteDelivery: false,
                displayCompanyAddress: false,
                displayCustomerVatId: false,
                displayDivergentDeliveryAddress: false,
                displayFooter: true,
                displayHeader: true,
                displayLineItemPosition: false,
                displayLineItems: true,
                displayPageCount: true,
                displayPrices: true,
                displayReturnAddress: false,
                fileTypes: [
                    'pdf',
                    'html',
                ],
                itemsPerPage: 10,
                pageOrientation: 'portrait',
                pageSize: 'a4',
            },
            documentType: {
                id: 'documentTypeId1',
                technicalName: 'invoice',
            },
            documentTypeId: 'documentTypeId1',
            filenameInfixes: {
                html: 'htmlInfix',
            },
            id: 'documentConfigWithFormats',
            salesChannels: [],
        });
    });

    it.each([
        {
            variant: 'the other format',
            field: 'pdf',
            parameters: { '{{ formats }}': 'zugferd_embedded_pdf, html' },
            snippet: 'sw-settings-document.errors.duplicateFilenameInfix',
            snippetParams: {
                formats:
                    'sw-order.components.createDocumentModal.fileFormats.zugferd_embedded_pdf, ' +
                    'sw-order.components.createDocumentModal.fileFormats.html',
            },
        },
        {
            variant: 'the affected sales channel configurations',
            field: 'zugferd_embedded_pdf',
            parameters: { '{{ formats }}': 'pdf', '{{ configs }}': 'Storefront invoice, B2B invoice' },
            snippet: 'sw-settings-document.errors.duplicateFilenameInfixInSalesChannelConfig',
            snippetParams: {
                formats: 'sw-order.components.createDocumentModal.fileFormats.pdf',
                configs: 'Storefront invoice, B2B invoice',
            },
        },
        {
            variant: 'the inherited infix on an empty field',
            field: 'zugferd_embedded_pdf',
            parameters: { '{{ formats }}': 'pdf', '{{ infix }}': '_zugferd' },
            snippet: 'sw-settings-document.errors.duplicateFilenameInfixInherited',
            snippetParams: {
                formats: 'sw-order.components.createDocumentModal.fileFormats.pdf',
                infix: '_zugferd',
            },
        },
    ])(
        'should show the duplicate filename infix error naming $variant',
        async ({ field, parameters, snippet, snippetParams }) => {
            documentBaseConfigRepositoryMock.save.mockImplementationOnce(() => {
                Shopware.Store.get('error').addApiError({
                    expression: `document_base_config.documentConfigWithFormats.filenameInfixes.${field}`,
                    error: new ShopwareError({
                        code: 'DOCUMENT_BASE_CONFIG_DUPLICATE_FILENAME_INFIX',
                        meta: { parameters },
                    }),
                });

                return Promise.reject({
                    response: { data: { errors: [{ code: 'DOCUMENT_BASE_CONFIG_DUPLICATE_FILENAME_INFIX' }] } },
                });
            });
            const translate = jest.spyOn(config.global.mocks, '$t');

            const wrapper = await createWrapper(
                {
                    props: { documentConfigId: 'documentConfigWithFormats' },
                },
                ['document.editor'],
                true,
            );
            await flushPromises();
            wrapper.vm.createNotificationError = jest.fn();

            await wrapper.get('.sw-settings-document-detail__save-action').trigger('click');
            await flushPromises();

            const fieldsWithError = wrapper
                .findAll('.sw-settings-document-detail__field_file_name_infix')
                .filter((infixField) => infixField.find('.mt-field__error').exists());
            expect(fieldsWithError).toHaveLength(1);
            expect(fieldsWithError[0].find(`#sw-field--documentConfig-filenameInfixes-${field}`).exists()).toBe(true);
            expect(wrapper.vm.createNotificationError).not.toHaveBeenCalled();
            expect(translate).toHaveBeenCalledWith(snippet, snippetParams);
            translate.mockRestore();
        },
    );
});
