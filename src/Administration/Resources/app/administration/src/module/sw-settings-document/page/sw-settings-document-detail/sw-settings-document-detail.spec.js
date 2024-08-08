import { mount } from '@vue/test-utils';

/**
 * @package customer-order
 * @group disabledCompat
 */

const documentBaseConfigRepositoryMock = {
    create: () => {
        return Promise.resolve({});
    },
    get: (id) => {
        const salesChannels = new Shopware.Data.EntityCollection(
            'source',
            'entity',
            Shopware.Context.api,
        );
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
            salesChannels.push({ id: 'associationId1', salesChannelId: 'salesChannelId1' });
            return Promise.resolve({
                id: id,
                documentTypeId: 'documentTypeId1',
                salesChannels: salesChannels,
                documentType: { id: 'documentTypeId1' },
            });
        }
        return Promise.resolve({
            id: id,
            documentTypeId: 'documentTypeId',
        });
    },
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
        const association = { id: `configSalesChannelId${documentBaseConfigSalesChannelsRepositoryMock.counter}` };
        documentBaseConfigSalesChannelsRepositoryMock.counter += 1;
        return association;
    },
    search: () => {
        return Promise.resolve([]);
    },
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

const createWrapper = async (customOptions, privileges = []) => {
    return mount(await wrapTestComponent('sw-settings-document-detail', {
        sync: true,
    }), {
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-page': {
                    template: `
                    <div class="sw-page">
                        <slot name="search-bar"></slot>
                        <slot name="smart-bar-back"></slot>
                        <slot name="smart-bar-header"></slot>
                        <slot name="language-switch"></slot>
                        <slot name="smart-bar-actions"></slot>
                        <slot name="side-content"></slot>
                        <slot name="content"></slot>
                        <slot name="sidebar"></slot>
                        <slot></slot>
                    </div>
                `,
                },
                'sw-entity-single-select': true,
                'sw-text-field': { template: '<div class="sw-field"/>', props: ['disabled'] },
                'sw-button': true,
                'sw-button-process': true,
                'sw-card-view': true,
                'sw-icon': true,
                'sw-card': true,
                'sw-container': true,
                'sw-form-field-renderer': true,
                'sw-language-switch': true,
                'sw-checkbox-field': {
                    template: `
                    <div class="sw-field--checkbox">
                        <div class="sw-field--checkbox__content">
                            <div class="sw-field__checkbox">
                                <input type="checkbox" />
                            </div>
                        </div>
                    </div>
                `,
                },
                'sw-entity-multi-id-select': true,
                'sw-entity-multi-select': true,
                'sw-select-base': true,
                'sw-base-field': true,
                'sw-field-error': true,
                'sw-media-field': { template: '<div id="sw-media-field"/>', props: ['disabled'] },
                'sw-multi-select': { template: '<div id="documentSalesChannel"/>', props: ['disabled'] },
                'sw-skeleton': true,
                'sw-select-result': true,
                'sw-highlight-text': true,
                'sw-custom-field-set-renderer': true,
            },
            provide: {
                repositoryFactory: {
                    create: (entity) => repositoryMockFactory(entity),
                },
                acl: {
                    can: key => (key ? privileges.includes(key) : true),
                },
                customFieldDataProviderService: {
                    getCustomFieldSets: () => Promise.resolve([]),
                },
            },
        },
        ...customOptions,
    });
};

describe('src/module/sw-settings-document/page/sw-settings-document-detail', () => {
    beforeEach(async () => {
        documentBaseConfigSalesChannelsRepositoryMock.counter = 1;
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    // eslint-disable-next-line max-len
    it('should create an array with sales channel ids from the document config sales channels association', async () => {
        const wrapper = await createWrapper({
            props: { documentConfigId: 'documentConfigWithSalesChannels' },
        });

        await flushPromises();

        expect(wrapper.vm.documentConfigSalesChannels).toEqual(['associationId1']);
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

    it('should create an entity collection with document config sales channels associations with ' +
        'actual sales channels associations inside', async () => {
        const wrapper = await createWrapper({
            props: { documentConfigId: 'documentConfigWithDocumentTypeAndSalesChannels' },
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
    });

    it('should recreate sales channel options collection when type changes', async () => {
        const wrapper = await createWrapper({
            props: {
                documentConfigId: 'documentConfigWithDocumentTypeAndSalesChannels',
            },
        });

        await flushPromises();

        expect(wrapper.vm.documentConfigSalesChannels).toEqual(['associationId1']);

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
            { props: { documentConfigId: 'documentConfigWithDocumentTypeAndSalesChannels' } },
            ['document.editor'],
        );

        await flushPromises();

        expect(wrapper.find('.sw-settings-document-detail__save-action')
            .attributes().disabled).toBeUndefined();
        expect(wrapper.findComponent('#sw-media-field').props().disabled).toBe(false);
        expect(wrapper.findAllComponents('.sw-field').every(field => !field.props().disabled)).toBe(true);
        expect(wrapper.findComponent('#documentSalesChannel').props().disabled).toBe(false);
    });

    it('should not be able to edit', async () => {
        const wrapper = await createWrapper({
            props: { documentConfigId: 'documentConfigWithDocumentTypeAndSalesChannels' },
        });

        await flushPromises();

        expect(wrapper.find('.sw-settings-document-detail__save-action')
            .attributes().disabled).toBe('true');
        expect(wrapper.findComponent('#sw-media-field').props().disabled).toBe(true);
        expect(wrapper.findAllComponents('.sw-field')
            .every(field => field.props().disabled)).toBe(true);
        expect(wrapper.findComponent('#documentSalesChannel').props().disabled).toBe(true);
    });

    it('should create an invoice document with countries note delivery', async () => {
        const wrapper = await createWrapper({}, ['document.editor']);

        await wrapper.vm.$nextTick();
        await wrapper.setData({
            isShowDisplayNoteDelivery: true,
            documentConfig: {
                config: {
                    displayAdditionalNoteDelivery: true,
                },
            },
        });

        const displayAdditionalNoteDeliveryCheckbox = wrapper.find(
            '.sw-settings-document-detail__field_additional_note_delivery',
        );

        expect(displayAdditionalNoteDeliveryCheckbox.attributes('value')).toBe('true');
        expect(displayAdditionalNoteDeliveryCheckbox.attributes('label'))
            .toBe('sw-settings-document.detail.labelDisplayAdditionalNoteDelivery');
    });

    it('should contain field "display divergent delivery address" in invoice form field', async () => {
        const wrapper = await createWrapper({}, ['document.editor']);

        await wrapper.vm.$nextTick();
        await wrapper.setData({
            isShowDivergentDeliveryAddress: true,
        });

        const displayDivergentDeliveryAddress = wrapper.find(
            '.sw-settings-document-detail__field_divergent_delivery_address',
        );
        expect(displayDivergentDeliveryAddress).toBeDefined();
        expect(displayDivergentDeliveryAddress.attributes('label'))
            .toBe('sw-settings-document.detail.labelDisplayDivergentDeliveryAddress');
    });

    // eslint-disable-next-line max-len
    it('should not exist "display divergent delivery address" in general form field and company form field', async () => {
        const wrapper = await createWrapper({}, ['document.editor']);

        await wrapper.vm.$nextTick();

        const companyFormFields = wrapper.vm.companyFormFields;
        const generalFormFields = wrapper.vm.generalFormFields;

        const fieldDivergentDeliveryAddressInCompany = companyFormFields.find(
            companyFormField => companyFormField && companyFormField.name === 'displayDivergentDeliveryAddress',
        );
        const fieldDivergentDeliveryAddressInGeneral = generalFormFields.find(
            generalFormField => generalFormField && generalFormField.name === 'displayDivergentDeliveryAddress',
        );
        expect(fieldDivergentDeliveryAddressInCompany).toBeUndefined();
        expect(fieldDivergentDeliveryAddressInGeneral).toBeUndefined();
    });

    it('should be have config company phone number', async () => {
        const wrapper = await createWrapper({}, ['document.editor']);

        await wrapper.vm.$nextTick();

        const companyFormFields = wrapper.vm.companyFormFields;

        expect(
            companyFormFields.map(item => item && item.name),
        ).toContain('companyPhone');

        const fieldCompanyPhone = companyFormFields.find(
            item => item && item.name === 'companyPhone',
        );
        expect(fieldCompanyPhone).toBeDefined();
        expect(fieldCompanyPhone).toEqual(
            expect.objectContaining({
                name: 'companyPhone',
                type: 'text',
                config: {
                    type: 'text',
                    label: expect.any(String),
                },
            }),
        );
    });

    /* @deprecated: tag:v6.7.0 - Remove this test */
    // eslint-disable-next-line max-len
    it('should be have countries in country select when have toggle display intra-community delivery checkbox', async () => {
        const wrapper = await createWrapper({}, ['document.editor']);

        await wrapper.vm.$nextTick();
        await wrapper.setData({
            isShowDisplayNoteDelivery: true,
            documentConfig: {
                config: {
                    deliveryCountries: [
                        '0110c22a5a92481aa8722a782dfc2573',
                        '0143d24eb0264eb89cc34f50d427b828',
                    ],
                },
            },
        });

        const displayAdditionalNoteDeliveryCheckbox = wrapper.find(
            '.sw-settings-document-detail__field_additional_note_delivery input',
        );

        await displayAdditionalNoteDeliveryCheckbox.setChecked();

        expect(displayAdditionalNoteDeliveryCheckbox.element.checked).toBe(true);
        expect(wrapper.vm.documentConfig.config.deliveryCountries).toHaveLength(2);
    });

    it('should have assignment card at the top of the page', async () => {
        const wrapper = await createWrapper(
            { props: { documentConfigId: 'documentConfigWithDocumentTypeAndSalesChannels' } },
            ['document.editor'],
        );

        await flushPromises();

        const swCardComponents = wrapper.findAll('sw-card-stub');

        expect(swCardComponents.length).toBeGreaterThan(0);
        expect(swCardComponents.at(0).attributes()['position-identifier']).toBe('sw-settings-document-detail-assignment');
    });
});
