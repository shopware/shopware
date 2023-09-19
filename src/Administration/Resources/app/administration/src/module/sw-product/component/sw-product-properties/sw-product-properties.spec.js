/*
 * @package inventory
 */

import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import swProductProperties from 'src/module/sw-product/component/sw-product-properties';
import 'src/app/component/entity/sw-entity-listing';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/utils/sw-inherit-wrapper';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-simple-search-field';
import EntityCollection from '../../../../core/data/entity-collection.data';

Shopware.Component.register('sw-product-properties', swProductProperties);

const { State } = Shopware;

const productPropertiesMock = [
    { id: '01', groupId: 'sizeId', name: '30', translated: { name: '30' } },
    { id: '02', groupId: 'sizeId', name: '32', translated: { name: '32' } },
    { id: '03', groupId: 'colorId', name: 'white', translated: { name: 'white' } },
    { id: '04', groupId: 'colorId', name: 'black', translated: { name: 'black' } },
];

const propertiesMock = [
    {
        id: 'sizeId',
        name: 'size',
        translated: {
            name: 'size',
        },
        options: productPropertiesMock.filter((item) => {
            return item.groupId === 'sizeId';
        }),
    },
    {
        id: 'colorId',
        name: 'color',
        translated: {
            name: 'color',
        },
        options: productPropertiesMock.filter((item) => {
            return item.groupId === 'colorId';
        }),
    },
];

function getPropertyCollection(propertiesMockIndex = -1) {
    return new EntityCollection(
        '/property-group-option',
        'property_group_option',
        null,
        { isShopwareContext: true },
        (propertiesMockIndex > -1 ? [propertiesMock[propertiesMockIndex]] : propertiesMock),
        propertiesMock.length,
        null,
    );
}

function getProductPropertyCollection() {
    return new EntityCollection(
        '/property-group-option',
        'property_group_option',
        null,
        { isShopwareContext: true },
        productPropertiesMock,
        productPropertiesMock.length,
        null,
    );
}

const productMock = {
    name: 'productMock',
    id: 'productId',
    parentId: 'parentProductId',
    properties: [],
};

const parentProductMock = {
    name: 'productMock',
    id: 'parentProductId',
    parentId: null,
    properties: getProductPropertyCollection(),
};

const $refsMock = {
    entityListing: {
        deleteId: null,
        showBulkDeleteModal: false,
        selection: {
            1: propertiesMock[1],
        },
    },
};

const propertyGroupRepositoryMock = {
    search: jest.fn(() => {
        return Promise.resolve(getPropertyCollection());
    }),
};

const repositoryMockFactory = (entity) => {
    if (entity === 'property_group') {
        return propertyGroupRepositoryMock;
    }

    if (entity === 'property_group_option') {
        return {
            search: () => {
                return Promise.resolve({ total: 0 });
            },
        };
    }

    throw new Error(`Repository for ${entity} is not implemented`);
};

async function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(await Shopware.Component.build('sw-product-properties'), {
        localVue,
        stubs: {
            'sw-inheritance-switch': {
                props: ['isInherited', 'disabled'],
                template: `
                    <div class="sw-inheritance-switch">
                        <div v-if="isInherited"
                            class="sw-inheritance-switch--is-inherited"
                            @click="onClickRemoveInheritance">
                        </div>
                        <div v-else
                             class="sw-inheritance-switch--is-not-inherited"
                             @click="onClickRestoreInheritance">
                        </div>
                    </div>`,
                methods: {
                    onClickRestoreInheritance() {
                        this.$emit('inheritance-restore');
                    },
                    onClickRemoveInheritance() {
                        this.$emit('inheritance-remove');
                    },
                },
            },
            'sw-inherit-wrapper': await Shopware.Component.build('sw-inherit-wrapper'),
            'sw-card': {
                template: `
                    <div class="sw-card">
                        <slot></slot>
                        <slot name="title"></slot>
                        <slot name="grid"></slot>
                    </div>
                `,
            },
            'sw-container': true,
            'sw-card-section': true,
            'sw-entity-listing': await Shopware.Component.build('sw-entity-listing'),
            'sw-empty-state': true,
            'sw-product-add-properties-modal': true,
            'sw-loader': true,
            'sw-simple-search-field': await Shopware.Component.build('sw-simple-search-field'),
            'sw-text-field': {
                template: '<input class="sw-text-field" @input="$emit(\'input\', $event.target.value)" />',
            },
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-label': true,
            'sw-icon': true,
            'sw-checkbox-field': {
                template: '<input class="sw-checkbox-field" type="checkbox" @change="$emit(\'change\', $event.target.value)" />',
            },
            'sw-pagination': true,
            'sw-context-menu-item': true,
            'sw-context-button': true,
            'router-link': true,
            'sw-modal': {
                template: '<div class="sw-modal"><slot name="modal-footer"></slot></div>',
            },
        },
        provide: {
            repositoryFactory: {
                create: (entity) => repositoryMockFactory(entity),
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                },
            },
            validationService: {},
        },
    });
}

describe('src/module/sw-product/component/sw-product-properties', () => {
    beforeAll(() => {
        State.registerModule('swProductDetail', {
            namespaced: true,
            state: {
                product: productMock,
                parentProduct: parentProductMock,
            },
            mutations: {
                setProduct(state, newProduct) {
                    state.product = newProduct;
                },
            },
            getters: {
                isLoading: () => false,
                isChild: () => true,
            },
        });
    });

    afterEach(() => {
        parentProductMock.properties = getProductPropertyCollection();
        jest.clearAllMocks();
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should get group ids successful', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.groupIds).toEqual(
            expect.arrayContaining(['sizeId', 'colorId']),
        );
    });

    it('should get group ids failed', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.groupIds).toEqual(
            expect.arrayContaining([]),
        );
    });

    it('should get properties successful', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.properties).toEqual(
            expect.arrayContaining(getPropertyCollection()),
        );
    });

    it('should get properties failed', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.properties).toEqual(
            expect.arrayContaining([]),
        );
    });

    it('should get properties failed if having no inputs', async () => {
        parentProductMock.properties = [];

        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.properties).toEqual(
            expect.arrayContaining([]),
        );
    });

    it('should delete property value successful', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.onDeletePropertyValue(productPropertiesMock[0]);

        expect(wrapper.vm.productProperties).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ id: '02', groupId: 'sizeId', name: '32' }),
                expect.objectContaining({ id: '03', groupId: 'colorId', name: 'white' }),
                expect.objectContaining({ id: '04', groupId: 'colorId', name: 'black' }),
            ]),
        );
    });

    it('should delete property successful', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        await wrapper.setData({ $refs: $refsMock });

        wrapper.vm.onDeleteProperty(propertiesMock[0]);

        expect(wrapper.vm.productProperties).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ id: '03', groupId: 'colorId', name: 'white' }),
                expect.objectContaining({ id: '04', groupId: 'colorId', name: 'black' }),
            ]),
        );
    });

    it('should delete all properties successful', async () => {
        const wrapper = await createWrapper([
            'product.deleter',
        ]);
        await flushPromises();

        await wrapper.find('.sw-data-grid__select-all').setChecked(true);
        await wrapper.find('.sw-data-grid__select-all').trigger('change');

        await wrapper.find('.sw-data-grid__bulk-selected .link-danger').trigger('click');

        await wrapper.find('.sw-modal .sw-button--danger').trigger('click');
        await flushPromises();

        expect(wrapper.vm.productHasProperties).toBe(false);
        expect(wrapper.find('.sw-product-properties__list').exists()).toBe(false);
        expect(wrapper.find('.sw-product-properties__empty-state.has--no-properties').exists()).toBe(true);
        expect(wrapper.vm.productProperties).toHaveLength(0);
    });

    it('should get properties when changing search term', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.onChangeSearchTerm('textile');

        expect(wrapper.vm.searchTerm).toBe('textile');
        expect(wrapper.vm.propertyGroupCriteria.term).toBe('textile');
    });

    it('should display an empty state if product has no properties', async () => {
        parentProductMock.properties = [];

        const wrapper = await createWrapper();

        await flushPromises();

        expect(wrapper.vm.productHasProperties).toBe(false);
        expect(wrapper.find('.sw-product-properties__list').exists()).toBe(false);
        expect(wrapper.find('.sw-product-properties__empty-state.has--no-properties').exists()).toBe(true);
    });

    it('should display the properties if product has properties', async () => {
        const wrapper = await createWrapper();

        await flushPromises();

        expect(wrapper.vm.productHasProperties).toBe(true);
        expect(wrapper.find('.sw-product-properties__list').exists()).toBe(true);
        expect(wrapper.find('.sw-product-properties__empty-state.has--no-properties').exists()).toBe(false);
    });

    it('should get searched properties successfully, if the product has properties', async () => {
        jest.useFakeTimers();

        const wrapper = await createWrapper();
        await flushPromises();

        propertyGroupRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getPropertyCollection(0));
        });

        const searchField = wrapper.find('.sw-simple-search-field input');
        await searchField.setValue('size');
        jest.advanceTimersByTime(1000);
        await flushPromises();

        expect(wrapper.vm.productHasProperties).toBe(true);
        expect(wrapper.find('.sw-product-properties__list').exists()).toBe(true);
        expect(wrapper.find('.sw-product-properties__empty-state.has--no-search-result').exists()).toBe(false);
    });

    it('should get searched properties unsuccessfully, if the product has properties', async () => {
        jest.useFakeTimers();

        const wrapper = await createWrapper();
        await flushPromises();

        propertyGroupRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.reject();
        });

        const searchField = wrapper.find('.sw-simple-search-field input');
        await searchField.setValue('Test');
        jest.advanceTimersByTime(1000);
        await flushPromises();

        expect(wrapper.vm.productHasProperties).toBe(true);
        expect(wrapper.find('.sw-product-properties__list').exists()).toBe(false);
        expect(wrapper.find('.sw-product-properties__empty-state.has--no-search-result').exists()).toBe(true);
    });

    it('should get all properties again after clearing a non successful search, if the product has properties', async () => {
        jest.useFakeTimers();

        const wrapper = await createWrapper();
        await flushPromises();

        propertyGroupRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.reject();
        });

        const searchField = wrapper.find('.sw-simple-search-field input');
        await searchField.setValue('Test');
        jest.advanceTimersByTime(1000);
        await flushPromises();

        await searchField.setValue(null);
        jest.advanceTimersByTime(1000);
        await flushPromises();

        expect(wrapper.vm.productHasProperties).toBe(true);
        expect(wrapper.find('.sw-product-properties__list').exists()).toBe(true);
        expect(wrapper.find('.sw-product-properties__empty-state.has--no-search-result').exists()).toBe(false);
    });

    it('should get all properties again after clearing a successful search, if the product has properties', async () => {
        jest.useFakeTimers();

        const wrapper = await createWrapper();
        await flushPromises();

        propertyGroupRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getPropertyCollection(0));
        });

        const searchField = wrapper.find('.sw-simple-search-field input');
        await searchField.setValue('size');
        jest.advanceTimersByTime(1000);
        await flushPromises();

        await searchField.setValue(null);
        jest.advanceTimersByTime(1000);
        await flushPromises();

        expect(wrapper.vm.productHasProperties).toBe(true);
        expect(wrapper.find('.sw-product-properties__list').exists()).toBe(true);
        expect(wrapper.find('.sw-product-properties__empty-state.has--no-search-result').exists()).toBe(false);
    });

    it('should turn on add properties modal', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        await wrapper.setData({
            propertiesAvailable: true,
        });
        wrapper.vm.updateNewProperties = jest.fn();

        wrapper.vm.turnOnAddPropertiesModal();

        expect(wrapper.vm.updateNewProperties).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.showAddPropertiesModal).toBe(true);
        wrapper.vm.updateNewProperties.mockRestore();
    });

    it('should turn off add properties modal', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        wrapper.vm.updateNewProperties = jest.fn();

        wrapper.vm.turnOffAddPropertiesModal();

        expect(wrapper.vm.showAddPropertiesModal).toBe(false);
        expect(wrapper.vm.updateNewProperties).toHaveBeenCalledTimes(1);
        wrapper.vm.updateNewProperties.mockRestore();
    });

    it('should update new properties correctly', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.updateNewProperties();

        expect(wrapper.vm.newProperties).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    id: '01',
                    groupId: 'sizeId',
                    name: '30',
                }),
                expect.objectContaining({
                    id: '02',
                    groupId: 'sizeId',
                    name: '32',
                }),
                expect.objectContaining({
                    id: '03',
                    groupId: 'colorId',
                    name: 'white',
                }),
                expect.objectContaining({
                    id: '04',
                    groupId: 'colorId',
                    name: 'black',
                }),
            ]),
        );
    });

    it('should call a turning off modal function when canceling properties modal', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        wrapper.vm.turnOffAddPropertiesModal = jest.fn();

        wrapper.vm.onCancelAddPropertiesModal();

        expect(wrapper.vm.turnOffAddPropertiesModal).toHaveBeenCalledTimes(1);
        wrapper.vm.turnOffAddPropertiesModal.mockRestore();
    });

    it('should save add properties modal failed', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        wrapper.vm.turnOffAddPropertiesModal = jest.fn();

        wrapper.vm.onSaveAddPropertiesModal([]);

        expect(wrapper.vm.turnOffAddPropertiesModal).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.newProperties).toEqual(
            expect.arrayContaining([]),
        );
        wrapper.vm.turnOffAddPropertiesModal.mockRestore();
    });

    it('should be able to add properties in empty state', async () => {
        parentProductMock.properties = [];

        const wrapper = await createWrapper([
            'product.editor',
        ]);
        await flushPromises();

        const createButton = wrapper.find('.sw-product-properties__empty-state.has--no-properties .sw-button');

        expect(createButton.attributes().disabled).toBeUndefined();
    });

    it('should not be able to add properties in empty state', async () => {
        parentProductMock.properties = [];

        const wrapper = await createWrapper();
        await flushPromises();

        const createButton = wrapper.find('.sw-product-properties__empty-state.has--no-properties .sw-button');
        expect(createButton.attributes().disabled).toBe('disabled');
    });

    it('should be able to add properties in filled state', async () => {
        const wrapper = await createWrapper([
            'product.editor',
        ]);
        await flushPromises();

        const createButton = wrapper.find('.sw-product-properties__button-add-property');

        expect(createButton.attributes().disabled).toBeUndefined();
    });

    it('should not be able to add properties in filled state', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({ properties: getPropertyCollection() });

        const createButton = wrapper.find('.sw-product-properties__button-add-property');
        expect(createButton.attributes().disabled).toBe('disabled');
    });

    it('should be able to edit property', async () => {
        const wrapper = await createWrapper([
            'property.editor',
        ]);
        await flushPromises();

        const entityListing = wrapper.find('.sw-product-properties__list');
        expect(entityListing.props('allowEdit')).toBe(true);
    });

    it('should not be able to edit property', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const entityListing = wrapper.find('.sw-product-properties__list');
        expect(entityListing.attributes()['allow-edit']).toBeUndefined();
    });

    it('should be able to delete property', async () => {
        const wrapper = await createWrapper([
            'product.deleter',
        ]);
        await flushPromises();

        const entityListing = wrapper.find('.sw-product-properties__list');
        expect(entityListing.props('allowDelete')).toBe(true);
    });

    it('should not be able to delete property', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const entityListing = wrapper.find('.sw-product-properties__list');
        expect(entityListing.props('allowDelete')).toBe(false);
    });

    it('should hide sw-inheritance-switch component', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-inheritance-switch').exists()).toBeTruthy();

        await wrapper.setProps({
            showInheritanceSwitcher: false,
        });
        expect(wrapper.vm.showInheritanceSwitcher).toBe(false);

        expect(wrapper.find('.sw-inheritance-switch').exists()).toBeFalsy();
    });

    it('should return filters from filter registry', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.assetFilter).toEqual(expect.any(Function));
    });
});
