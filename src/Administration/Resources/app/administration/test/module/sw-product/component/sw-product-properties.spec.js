import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/module/sw-product/component/sw-product-properties';

const { Component, State } = Shopware;

let productPropertiesMock = [
    { id: '01', groupId: 'sizeId', name: '30' },
    { id: '02', groupId: 'sizeId', name: '32' },
    { id: '03', groupId: 'colorId', name: 'white' },
    { id: '04', groupId: 'colorId', name: 'black' }
];

productPropertiesMock.getIds = () => {
    return productPropertiesMock.map(property => {
        return property.id;
    });
};

productPropertiesMock.remove = (id) => {
    productPropertiesMock = productPropertiesMock.filter((item) => {
        return item.id !== id;
    });
};
productPropertiesMock.has = (id) => {
    return productPropertiesMock.some((item) => {
        return item.id === id;
    });
};
productPropertiesMock.add = (item) => {
    productPropertiesMock.push(item);
};

const propertiesMock = [
    {
        id: 'sizeId',
        name: 'size',
        translated: {
            name: 'size'
        },
        options: productPropertiesMock.filter((item) => {
            return item.groupId === 'sizeId';
        })
    },
    {
        id: 'colorId',
        name: 'color',
        translated: {
            name: 'color'
        },
        options: productPropertiesMock.filter((item) => {
            return item.groupId === 'colorId';
        })
    }
];

const productMock = {
    id: 'productId',
    parentId: 'parentProductId',
    properties: []
};

const parentProductMock = {
    id: 'parentProductId',
    parentId: null,
    properties: productPropertiesMock
};

const $refsMock = {
    entityListing: {
        deleteId: null,
        showBulkDeleteModal: false,
        selection: {
            1: propertiesMock[1]
        }
    }
};

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(Component.build('sw-product-properties'), {
        localVue,
        stubs: {
            'sw-inherit-wrapper': {
                template: `
                    <div class="sw-inherit-wrapper">
                        <slot name="content"></slot>
                    </div>
                `
            },
            'sw-card': {
                template: `
                    <div class="sw-card">
                        <slot></slot>
                        <slot name="grid"></slot>
                    </div>
                `
            },
            'sw-container': {
                template: `
                    <div class="sw-container">
                        <slot></slot>
                    </div>
                `
            },
            'sw-card-section': {
                template: `
                    <div class="sw-card-section">
                        <slot></slot>
                    </div>
                `
            },
            'sw-entity-listing': {
                props: ['items'],
                template: `
                    <div class="sw-entity-listing">
                        <template v-for="item in items">
                            <slot name="actions" v-bind="{ item }"></slot>
                        </template>
                    </div>
                `
            },
            'sw-empty-state': {
                template: `
                    <div class="sw-empty-state">
                        <slot></slot>
                        <slot name="actions"></slot>
                    </div>
                `
            },
            'sw-product-add-properties-modal': true,
            'sw-loader': true,
            'sw-simple-search-field': true,
            'sw-button': true,
            'sw-icon': true
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => {
                        return Promise.resolve();
                    }
                })
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                }
            }
        }
    });
}

describe('src/module/sw-product/component/sw-product-properties', () => {
    beforeAll(() => {
        State.registerModule('swProductDetail', {
            namespaced: true,
            state: {
                product: productMock,
                parentProduct: parentProductMock
            },
            mutations: {
                setProduct(state, newProduct) {
                    state.product = newProduct;
                }
            },
            getters: {
                isLoading: () => false,
                isChild: () => true
            }
        });
    });

    it('should be a Vue.JS component', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should get group ids successful', async () => {
        const wrapper = createWrapper();

        await wrapper.vm.$nextTick();
        await State.commit('swProductDetail/setProduct', productMock);
        await wrapper.vm.getGroupIds();

        expect(wrapper.vm.groupIds).toEqual(
            expect.arrayContaining(['sizeId', 'colorId'])
        );
    });

    it('should get group ids failed', async () => {
        const wrapper = createWrapper();

        await wrapper.vm.$nextTick();
        await State.commit('swProductDetail/setProduct', {});
        await wrapper.vm.getGroupIds();

        expect(wrapper.vm.groupIds).toEqual(
            expect.arrayContaining([])
        );
    });

    it('should get properties successful', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.propertyGroupRepository.search = jest.fn(() => {
            return Promise.resolve(propertiesMock);
        });

        await State.commit('swProductDetail/setProduct', productMock);
        await wrapper.vm.getGroupIds();
        wrapper.vm.getProperties();

        expect(wrapper.vm.properties).toEqual(
            expect.arrayContaining(propertiesMock)
        );
        wrapper.vm.propertyGroupRepository.search.mockRestore();
    });

    it('should get properties failed', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.propertyGroupRepository.search = jest.fn(() => {
            return Promise.reject();
        });

        await State.commit('swProductDetail/setProduct', productMock);
        await wrapper.vm.getGroupIds();
        wrapper.vm.getProperties();

        expect(wrapper.vm.properties).toEqual(
            expect.arrayContaining([])
        );
        wrapper.vm.propertyGroupRepository.search.mockRestore();
    });

    it('should get properties failed if having no inputs', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.getProperties = jest.fn(() => {
            return Promise.reject(new Error('Whoops!'));
        });

        await State.commit('swProductDetail/setProduct', productMock);
        wrapper.vm.getProperties().catch((error) => {
            expect(error.message).toBe('Whoops!');
        });

        expect(wrapper.vm.properties).toEqual(
            expect.arrayContaining([])
        );
        wrapper.vm.getProperties.mockRestore();
    });

    it('should delete property value successful', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.propertyGroupRepository.search = jest.fn(() => {
            return Promise.resolve(propertiesMock);
        });

        await State.commit('swProductDetail/setProduct', productMock);
        await wrapper.vm.getGroupIds();
        await wrapper.vm.getProperties();

        wrapper.vm.onDeletePropertyValue(productPropertiesMock[0]);

        expect(wrapper.vm.productProperties).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ id: '02', groupId: 'sizeId', name: '32' }),
                expect.objectContaining({ id: '03', groupId: 'colorId', name: 'white' }),
                expect.objectContaining({ id: '04', groupId: 'colorId', name: 'black' })
            ])
        );
        wrapper.vm.propertyGroupRepository.search.mockRestore();
    });

    it('should delete property successful', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        await wrapper.setData({ $refs: $refsMock });
        wrapper.vm.propertyGroupRepository.search = jest.fn(() => {
            return Promise.resolve(propertiesMock);
        });

        await State.commit('swProductDetail/setProduct', productMock);
        await wrapper.vm.getGroupIds();
        await wrapper.vm.getProperties();

        wrapper.vm.onDeleteProperty(propertiesMock[0]);

        expect(wrapper.vm.productProperties).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ id: '03', groupId: 'colorId', name: 'white' }),
                expect.objectContaining({ id: '04', groupId: 'colorId', name: 'black' })
            ])
        );
        wrapper.vm.propertyGroupRepository.search.mockRestore();
    });

    it('should delete properties successful', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        await wrapper.setData({ $refs: $refsMock });
        wrapper.vm.propertyGroupRepository.search = jest.fn(() => {
            return Promise.resolve(propertiesMock);
        });

        await State.commit('swProductDetail/setProduct', productMock);
        await wrapper.vm.getGroupIds();
        await wrapper.vm.getProperties();

        wrapper.vm.onDeleteProperties();

        expect(wrapper.vm.productProperties).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ id: '01', groupId: 'sizeId', name: '30' }),
                expect.objectContaining({ id: '02', groupId: 'sizeId', name: '32' })
            ])
        );
        wrapper.vm.propertyGroupRepository.search.mockRestore();
    });

    it('should get properties when changing search term', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.getProperties = jest.fn(() => {
            return Promise.reject(new Error('Whoops!'));
        });

        await wrapper.vm.onChangeSearchTerm('textile');

        expect(wrapper.vm.searchTerm).toBe('textile');
        expect(wrapper.vm.propertyGroupCriteria.term).toBe('textile');
        expect(wrapper.vm.getProperties).toHaveBeenCalledTimes(1);
        wrapper.vm.getProperties.mockRestore();
    });

    it('should turn on add properties modal', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.updateNewProperties = jest.fn();

        wrapper.vm.turnOnAddPropertiesModal();

        expect(wrapper.vm.updateNewProperties).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.showAddPropertiesModal).toBe(true);
        wrapper.vm.updateNewProperties.mockRestore();
    });

    it('should turn off add properties modal', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.updateNewProperties = jest.fn();

        wrapper.vm.turnOffAddPropertiesModal();

        expect(wrapper.vm.showAddPropertiesModal).toBe(false);
        expect(wrapper.vm.updateNewProperties).toHaveBeenCalledTimes(1);
        wrapper.vm.updateNewProperties.mockRestore();
    });

    it('should update new properties correctly', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.propertyGroupRepository.search = jest.fn(() => {
            return Promise.resolve(propertiesMock);
        });

        await State.commit('swProductDetail/setProduct', productMock);
        await wrapper.vm.getGroupIds();
        await wrapper.vm.getProperties();

        wrapper.vm.updateNewProperties();

        expect(wrapper.vm.newProperties).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    property: expect.objectContaining({
                        id: '01',
                        groupId: 'sizeId',
                        name: '30'
                    }),
                    selected: true
                }),
                expect.objectContaining({
                    property: expect.objectContaining({
                        id: '02',
                        groupId: 'sizeId',
                        name: '32'
                    }),
                    selected: true
                }),
                expect.objectContaining({
                    property: expect.objectContaining({
                        id: '03',
                        groupId: 'colorId',
                        name: 'white'
                    }),
                    selected: true
                }),
                expect.objectContaining({
                    property: expect.objectContaining({
                        id: '04',
                        groupId: 'colorId',
                        name: 'black'
                    }),
                    selected: true
                })
            ])
        );
        wrapper.vm.propertyGroupRepository.search.mockRestore();
    });

    it('should update new properties item correctly', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.propertyGroupRepository.search = jest.fn(() => {
            return Promise.resolve(propertiesMock);
        });

        await State.commit('swProductDetail/setProduct', productMock);
        await wrapper.vm.getGroupIds();
        await wrapper.vm.getProperties();

        wrapper.vm.updateNewProperties();
        wrapper.vm.updateNewPropertiesItem({
            index: 0,
            selected: false
        });

        expect(wrapper.vm.newProperties[0].selected).toBe(false);
        wrapper.vm.propertyGroupRepository.search.mockRestore();
    });

    it('should add new properties item successful', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.propertyGroupRepository.search = jest.fn(() => {
            return Promise.resolve(propertiesMock);
        });

        await State.commit('swProductDetail/setProduct', productMock);
        await wrapper.vm.getGroupIds();
        await wrapper.vm.getProperties();

        wrapper.vm.updateNewProperties();
        wrapper.vm.addNewPropertiesItem({
            property: {
                id: '05',
                groupId: 'colorId',
                name: 'blue'
            },
            selected: true
        });

        expect(wrapper.vm.newProperties).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    property: expect.objectContaining({
                        id: '05',
                        groupId: 'colorId',
                        name: 'blue'
                    }),
                    selected: true
                })
            ])
        );
        wrapper.vm.propertyGroupRepository.search.mockRestore();
    });

    it('should call a turning off modal function when canceling properties modal', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.turnOffAddPropertiesModal = jest.fn();

        wrapper.vm.onCancelAddPropertiesModal();

        expect(wrapper.vm.turnOffAddPropertiesModal).toHaveBeenCalledTimes(1);
        wrapper.vm.turnOffAddPropertiesModal.mockRestore();
    });

    it('should call an adding function when saving properties modal', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.propertyGroupRepository.search = jest.fn(() => {
            return Promise.resolve(propertiesMock);
        });
        wrapper.vm.productProperties.add = jest.fn();

        await State.commit('swProductDetail/setProduct', productMock);
        await wrapper.vm.getGroupIds();
        await wrapper.vm.getProperties();

        wrapper.vm.updateNewProperties();
        wrapper.vm.onSaveAddPropertiesModal([
            {
                property: {
                    id: '05',
                    groupId: 'colorId',
                    name: 'blue'
                },
                selected: true
            }
        ]);

        expect(wrapper.vm.productProperties.add).toHaveBeenCalledWith({
            id: '05',
            groupId: 'colorId',
            name: 'blue'
        });
        wrapper.vm.propertyGroupRepository.search.mockRestore();
        wrapper.vm.productProperties.add.mockRestore();
    });

    it('should call a removing function when saving properties modal', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.propertyGroupRepository.search = jest.fn(() => {
            return Promise.resolve(propertiesMock);
        });
        wrapper.vm.productProperties.remove = jest.fn();

        await State.commit('swProductDetail/setProduct', productMock);
        await wrapper.vm.getGroupIds();
        await wrapper.vm.getProperties();

        wrapper.vm.updateNewProperties();
        wrapper.vm.onSaveAddPropertiesModal([
            {
                property: {
                    id: '01',
                    groupId: 'sizeId',
                    name: '30'
                },
                selected: false
            }
        ]);

        expect(wrapper.vm.productProperties.remove).toHaveBeenCalledWith('01');
        wrapper.vm.propertyGroupRepository.search.mockRestore();
        wrapper.vm.productProperties.remove.mockRestore();
    });

    it('should save add properties modal failed', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.turnOffAddPropertiesModal = jest.fn();

        wrapper.vm.onSaveAddPropertiesModal([]);

        expect(wrapper.vm.turnOffAddPropertiesModal).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.newProperties).toEqual(
            expect.arrayContaining([])
        );
        wrapper.vm.turnOffAddPropertiesModal.mockRestore();
    });

    it('should be able to add properties in empty state', async () => {
        const wrapper = createWrapper([
            'product.editor'
        ]);
        await wrapper.vm.$nextTick();

        await wrapper.setData({ properties: [], searchTerm: null });

        const createButton = wrapper.find('sw-button-stub');

        expect(createButton.attributes().disabled).toBe(undefined);
    });

    it('should not be able to add properties in empty state', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setData({ properties: [], searchTerm: null });

        const createButton = wrapper.find('sw-button-stub');
        expect(createButton.attributes().disabled).toBe('true');
    });

    it('should be able to add properties in filled state', async () => {
        const wrapper = createWrapper([
            'product.editor'
        ]);

        await wrapper.vm.$nextTick();

        await wrapper.setData({ searchTerm: 'Size', properties: propertiesMock });

        const createButton = wrapper.find('sw-button-stub');

        expect(createButton.attributes().disabled).toBe(undefined);
    });

    it('should not be able to add properties in filled state', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setData({ searchTerm: 'Size', properties: propertiesMock });

        const createButton = wrapper.find('sw-button-stub');
        expect(createButton.attributes().disabled).toBe('true');
    });

    it('should be able to edit property', async () => {
        const wrapper = createWrapper([
            'property.editor'
        ]);
        await wrapper.vm.$nextTick();
        wrapper.vm.propertyGroupRepository.search = jest.fn(() => {
            return Promise.resolve(propertiesMock);
        });

        await State.commit('swProductDetail/setProduct', productMock);
        await wrapper.vm.getGroupIds();
        await wrapper.vm.getProperties();

        const entityListing = wrapper.find('.sw-product-properties__list');
        expect(entityListing.attributes()['allow-edit']).toBe('true');

        wrapper.vm.propertyGroupRepository.search.mockRestore();
    });

    it('should not be able to edit property', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.propertyGroupRepository.search = jest.fn(() => {
            return Promise.resolve(propertiesMock);
        });

        await State.commit('swProductDetail/setProduct', productMock);
        await wrapper.vm.getGroupIds();
        await wrapper.vm.getProperties();

        const entityListing = wrapper.find('.sw-product-properties__list');
        expect(entityListing.attributes()['allow-edit']).toBe(undefined);

        wrapper.vm.propertyGroupRepository.search.mockRestore();
    });

    it('should be able to delete property', async () => {
        const wrapper = createWrapper([
            'product.deleter'
        ]);
        await wrapper.vm.$nextTick();
        wrapper.vm.propertyGroupRepository.search = jest.fn(() => {
            return Promise.resolve(propertiesMock);
        });

        await State.commit('swProductDetail/setProduct', productMock);
        await wrapper.vm.getGroupIds();
        await wrapper.vm.getProperties();

        const entityListing = wrapper.find('.sw-product-properties__list');
        expect(entityListing.attributes()['allow-delete']).toBe('true');

        wrapper.vm.propertyGroupRepository.search.mockRestore();
    });

    it('should not be able to delete property', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.propertyGroupRepository.search = jest.fn(() => {
            return Promise.resolve(propertiesMock);
        });

        await State.commit('swProductDetail/setProduct', productMock);
        await wrapper.vm.getGroupIds();
        await wrapper.vm.getProperties();

        const entityListing = wrapper.find('.sw-product-properties__list');
        expect(entityListing.attributes()['allow-delete']).toBe(undefined);

        wrapper.vm.propertyGroupRepository.search.mockRestore();
    });
});
