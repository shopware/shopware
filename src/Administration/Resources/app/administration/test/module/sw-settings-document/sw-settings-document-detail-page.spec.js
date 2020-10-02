import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-document/page/sw-settings-document-detail';

const documentBaseConfigRepositoryMock = {
    get: (id) => {
        const salesChannels = new Shopware.Data.EntityCollection('source', 'entity', Shopware.Context.api);
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
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    const options = {
        localVue,
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
            'sw-media-field': { template: '<div id="sw-media-field"/>', props: ['disabled'] },
            'sw-multi-select': { template: '<div id="documentSalesChannel"/>', props: ['disabled'] }
        },
        mocks: {
            $tc: snippetPath => snippetPath,
            $device: { getSystemKey: () => {} }
        },
        provide: {
            repositoryFactory: {
                create: (entity) => repositoryMockFactory(entity)
            },
            acl: {
                can: key => (key ? privileges.includes(key) : true)
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

    it('should create an array with sales channel ids from the document config sales channels association', async () => {
        const wrapper = createWrapper({ propsData: { documentConfigId: 'documentConfigWithSalesChannels' } });

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.documentConfigSalesChannels).toEqual(['associationId1']);
    });

    it('should create an entity collection with document config sales channels associations', async () => {
        const wrapper = createWrapper({ propsData: { documentConfigId: 'documentConfigWithDocumentType' } });

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
        const wrapper = createWrapper({ propsData: { documentConfigId: 'documentConfigWithDocumentTypeAndSalesChannels' } });

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
        const wrapper = createWrapper({ propsData: { documentConfigId: 'documentConfigWithDocumentTypeAndSalesChannels' } });

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

        expect(wrapper.find('.sw-settings-document-detail__save-action').attributes().disabled).toBeUndefined();
        expect(wrapper.find('#sw-media-field').props().disabled).toEqual(false);
        expect(wrapper.findAll('.sw-field').wrappers.every(field => !field.props().disabled)).toEqual(true);
        expect(wrapper.find('#documentSalesChannel').props().disabled).toEqual(false);
    });

    it('should not be able to edit', async () => {
        const wrapper = createWrapper({ propsData: { documentConfigId: 'documentConfigWithDocumentTypeAndSalesChannels' } });

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-settings-document-detail__save-action').attributes().disabled).toBe('true');
        expect(wrapper.find('#sw-media-field').props().disabled).toEqual(true);
        expect(wrapper.findAll('.sw-field').wrappers.every(field => field.props().disabled)).toEqual(true);
        expect(wrapper.find('#documentSalesChannel').props().disabled).toEqual(true);
    });
});
