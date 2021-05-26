import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-document/page/sw-settings-document-detail';

const documentBaseConfigRepositoryMock = {
    create: () => {
        return Promise.resolve({});
    },
    get: (id) => {
        const salesChannels = new Shopware.Data.EntityCollection(
            'source',
            'entity',
            Shopware.Context.api
        );
        if (id === 'documentConfigWithSalesChannels') {
            salesChannels.push({
                id: 'associationId1',
                salesChannelId: 'salesChannelId1'
            });
            return Promise.resolve({
                id: id,
                documentTypeId: 'documentTypeId1',
                salesChannels: salesChannels
            });
        }
        if (id === 'documentConfigWithDocumentType') {
            return Promise.resolve({
                id: id,
                documentTypeId: 'documentTypeId1',
                salesChannels: salesChannels,
                documentType: { id: 'documentTypeId1' }
            });
        }
        if (id === 'documentConfigWithDocumentTypeAndSalesChannels') {
            salesChannels.push({ id: 'associationId1', salesChannelId: 'salesChannelId1' });
            return Promise.resolve({
                id: id,
                documentTypeId: 'documentTypeId1',
                salesChannels: salesChannels,
                documentType: { id: 'documentTypeId1' }
            });
        }
        return Promise.resolve({
            id: id,
            documentTypeId: 'documentTypeId'
        });
    }
};
const salesChannelRepositoryMock = {
    search: () => {
        return [
            { id: 'salesChannelId1', name: 'salesChannel1' },
            { id: 'salesChannelId2', name: 'salesChannel2' }
        ];
    }
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
    }
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

const createWrapper = (customOptions, privileges = []) => {
    const options = {
        stubs: {
            'sw-page': true,
            'sw-entity-single-select': true,
            'sw-field': { template: '<div class="sw-field"/>', props: ['disabled'] },
            'sw-button': true,
            'sw-button-process': true,
            'sw-card-view': true,
            'sw-icon': true,
            'sw-card': true,
            'sw-container': true,
            'sw-form-field-renderer': true,
            'sw-checkbox-field': {
                template: `
                    <div class="sw-field--checkbox">
                        <div class="sw-field--checkbox__content">
                            <div class="sw-field__checkbox">
                                <input type="checkbox" />
                            </div>
                        </div>
                    </div>
                `
            },
            'sw-entity-multi-id-select': true,
            'sw-entity-multi-select': true,
            'sw-select-base': true,
            'sw-base-field': true,
            'sw-field-error': true,
            'sw-media-field': { template: '<div id="sw-media-field"/>', props: ['disabled'] },
            'sw-multi-select': { template: '<div id="documentSalesChannel"/>', props: ['disabled'] }
        },
        provide: {
            repositoryFactory: {
                create: (entity) => repositoryMockFactory(entity)
            },
            acl: {
                can: key => (key ? privileges.includes(key) : true)
            },
            customFieldDataProviderService: {
                getCustomFieldSets: () => Promise.resolve([])
            }
        }
    };

    return shallowMount(Shopware.Component.build('sw-settings-document-detail'), {
        ...options,
        ...customOptions
    });
};

describe('src/module/sw-settings-document/page/sw-settings-document-detail', () => {
    beforeEach(() => {
        documentBaseConfigSalesChannelsRepositoryMock.counter = 1;
    });

    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    // eslint-disable-next-line max-len
    it('should create an array with sales channel ids from the document config sales channels association', async () => {
        const wrapper = createWrapper({
            propsData: { documentConfigId: 'documentConfigWithSalesChannels' }
        });

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.documentConfigSalesChannels).toEqual(['associationId1']);
    });

    it('should create an entity collection with document config sales channels associations', async () => {
        const wrapper = createWrapper({
            propsData: { documentConfigId: 'documentConfigWithDocumentType' }
        });

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.documentConfigSalesChannelOptionsCollection[0]).toEqual({
            documentBaseConfigId: 'documentConfigWithDocumentType',
            documentTypeId: 'documentTypeId1',
            id: 'configSalesChannelId1',
            salesChannel: { id: 'salesChannelId1', name: 'salesChannel1' },
            salesChannelId: 'salesChannelId1'
        });
        expect(wrapper.vm.documentConfigSalesChannelOptionsCollection[1]).toEqual({
            documentBaseConfigId: 'documentConfigWithDocumentType',
            documentTypeId: 'documentTypeId1',
            id: 'configSalesChannelId2',
            salesChannel: { id: 'salesChannelId2', name: 'salesChannel2' },
            salesChannelId: 'salesChannelId2'
        });
    });

    it('should create an entity collection with document config sales channels associations with ' +
        'actual sales channels associations inside', async () => {
        const wrapper = createWrapper({
            propsData: { documentConfigId: 'documentConfigWithDocumentTypeAndSalesChannels' }
        });

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.documentConfigSalesChannelOptionsCollection[0]).toEqual({
            id: 'associationId1',
            salesChannelId: 'salesChannelId1'
        });
        expect(wrapper.vm.documentConfigSalesChannelOptionsCollection[1]).toEqual({
            documentBaseConfigId: 'documentConfigWithDocumentTypeAndSalesChannels',
            documentTypeId: 'documentTypeId1',
            id: 'configSalesChannelId1',
            salesChannel: { id: 'salesChannelId2', name: 'salesChannel2' },
            salesChannelId: 'salesChannelId2'
        });
    });

    it('should recreate sales channel options collection when type changes', async () => {
        const wrapper = createWrapper({
            propsData: {
                documentConfigId: 'documentConfigWithDocumentTypeAndSalesChannels'
            }
        });

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.documentConfigSalesChannels).toEqual(['associationId1']);

        wrapper.vm.onChangeType({ id: 'documentTypeId2' });

        expect(wrapper.vm.documentConfigSalesChannelOptionsCollection[0]).toEqual({
            id: 'associationId1',
            salesChannelId: 'salesChannelId1'
        });
        expect(wrapper.vm.documentConfigSalesChannelOptionsCollection[1]).toEqual({
            documentBaseConfigId: 'documentConfigWithDocumentTypeAndSalesChannels',
            documentTypeId: 'documentTypeId2',
            id: 'configSalesChannelId2',
            salesChannel: { id: 'salesChannelId2', name: 'salesChannel2' },
            salesChannelId: 'salesChannelId2'
        });

        expect(wrapper.vm.documentConfigSalesChannels).toEqual([]);
    });

    it('should be able to edit', async () => {
        const wrapper = createWrapper(
            { propsData: { documentConfigId: 'documentConfigWithDocumentTypeAndSalesChannels' } },
            ['document.editor']
        );

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();


        expect(wrapper.find('.sw-settings-document-detail__save-action')
            .attributes().disabled).toBeUndefined();
        expect(wrapper.find('#sw-media-field').props().disabled).toEqual(false);
        expect(wrapper.findAll('.sw-field')
            .wrappers.every(field => !field.props().disabled)).toEqual(true);
        expect(wrapper.find('#documentSalesChannel').props().disabled).toEqual(false);
    });

    it('should not be able to edit', async () => {
        const wrapper = createWrapper({
            propsData: { documentConfigId: 'documentConfigWithDocumentTypeAndSalesChannels' }
        });

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-settings-document-detail__save-action')
            .attributes().disabled).toBe('true');
        expect(wrapper.find('#sw-media-field').props().disabled).toEqual(true);
        expect(wrapper.findAll('.sw-field')
            .wrappers.every(field => field.props().disabled)).toEqual(true);
        expect(wrapper.find('#documentSalesChannel').props().disabled).toEqual(true);
    });

    it('should create an invoice document with countries note delivery', async () => {
        const wrapper = createWrapper({}, ['document.editor']);

        await wrapper.vm.$nextTick();
        await wrapper.setData({
            isShowDisplayNoteDelivery: true,
            documentConfig: {
                config: {
                    displayAdditionalNoteDelivery: true
                }
            }
        });

        const displayAdditionalNoteDeliveryCheckbox = wrapper.find(
            '.sw-settings-document-detail__field_additional_note_delivery'
        );
        const deliveryCountriesSelect = wrapper.find(
            '.sw-settings-document-detail__field_delivery_countries'
        );

        expect(displayAdditionalNoteDeliveryCheckbox.attributes('value')).toBe('true');
        expect(displayAdditionalNoteDeliveryCheckbox.attributes('label'))
            .toBe('sw-settings-document.detail.labelDisplayAdditionalNoteDelivery');
        expect(deliveryCountriesSelect.attributes('help-text'))
            .toBe('sw-settings-document.detail.helpTextDisplayDeliveryCountries');
        expect(deliveryCountriesSelect.attributes('label'))
            .toBe('sw-settings-document.detail.labelDeliveryCountries');
    });

    it('should contain field "display divergent delivery address" in invoice form field', async () => {
        const wrapper = createWrapper({}, ['document.editor']);

        await wrapper.vm.$nextTick();
        await wrapper.setData({
            isShowDivergentDeliveryAddress: true
        });

        const displayDivergentDeliveryAddress = wrapper.find(
            '.sw-settings-document-detail__field_divergent_delivery_address'
        );
        expect(displayDivergentDeliveryAddress).toBeDefined();
        expect(displayDivergentDeliveryAddress.attributes('label'))
            .toBe('sw-settings-document.detail.labelDisplayDivergentDeliveryAddress');
    });

    // eslint-disable-next-line max-len
    it('should not exist "display divergent delivery address" in general form field and company form field', async () => {
        const wrapper = createWrapper({}, ['document.editor']);

        await wrapper.vm.$nextTick();

        const companyFormFields = wrapper.vm.companyFormFields;
        const generalFormFields = wrapper.vm.generalFormFields;

        const fieldDivergentDeliveryAddressInCompany = companyFormFields.find(
            companyFormField => companyFormField && companyFormField.name === 'displayDivergentDeliveryAddress'
        );
        const fieldDivergentDeliveryAddressInGeneral = generalFormFields.find(
            generalFormField => generalFormField && generalFormField.name === 'displayDivergentDeliveryAddress'
        );
        expect(fieldDivergentDeliveryAddressInCompany).toBeUndefined();
        expect(fieldDivergentDeliveryAddressInGeneral).toBeUndefined();
    });

    it('should be have config company phone number', async () => {
        const wrapper = createWrapper({}, ['document.editor']);

        await wrapper.vm.$nextTick();

        const companyFormFields = wrapper.vm.companyFormFields;

        expect(
            companyFormFields.map(item => item && item.name).includes('companyPhone')
        ).toEqual(true);

        const fieldCompanyPhone = companyFormFields.find(
            item => item && item.name === 'companyPhone'
        );
        expect(fieldCompanyPhone).toBeDefined();
        expect(fieldCompanyPhone).toEqual(
            expect.objectContaining({
                name: 'companyPhone',
                type: 'text',
                config: {
                    type: 'text',
                    label: expect.any(String)
                }
            })
        );
    });

    // eslint-disable-next-line max-len
    it('should be have countries in country select when have toggle display intra-community delivery checkbox', async () => {
        const wrapper = createWrapper({}, ['document.editor']);

        await wrapper.vm.$nextTick();
        await wrapper.setData({
            isShowDisplayNoteDelivery: true,
            documentConfig: {
                config: {
                    deliveryCountries: [
                        '0110c22a5a92481aa8722a782dfc2573',
                        '0143d24eb0264eb89cc34f50d427b828'
                    ]
                }
            }
        });

        const displayAdditionalNoteDeliveryCheckbox = wrapper.find(
            '.sw-settings-document-detail__field_additional_note_delivery input'
        );

        displayAdditionalNoteDeliveryCheckbox.setChecked();

        expect(displayAdditionalNoteDeliveryCheckbox.element.checked).toBe(true);
        expect(wrapper.vm.documentConfig.config.deliveryCountries.length).toEqual(2);
    });
});
