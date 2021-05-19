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
productPropertiesMock.remove = (id) => {
    productPropertiesMock = productPropertiesMock.filter((item) => {
        return item.id !== id;
    });
};

const propertiesMock = [
    {
        id: 'sizeId',
        name: 'size',
        translated: {
            name: 'size'
        },
        values: productPropertiesMock.filter((item) => {
            return item.groupId === 'sizeId';
        })
    },
    {
        id: 'colorId',
        name: 'color',
        translated: {
            name: 'color'
        },
        values: productPropertiesMock.filter((item) => {
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
                        <slot name="content" />
                    </div>
                `
            },
            'sw-card': {
                template: `
                    <div class="sw-card">
                        <slot />
                        <slot name="grid" />
                    </div>
                `
            },
            'sw-container': {
                template: `
                    <div class="sw-container">
                        <slot />
                    </div>
                `
            },
            'sw-card-section': {
                template: `
                    <div class="sw-card-section">
                        <slot />
                    </div>
                `
            },
            'sw-entity-listing': {
                props: ['items'],
                template: `
                    <div class="sw-entity-listing">
                        <template v-for="item in items">
                            <slot name="actions" v-bind="{ item }" />
                        </template>
                    </div>
                `
            },
            'sw-empty-state': {
                template: `
                    <div class="sw-empty-state">
                        <slot />
                        <slot name="actions" />
                    </div>
                `
            },
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

        await wrapper.setData({ searchTerm: 'Size' });

        const createButton = wrapper.find('sw-button-stub');
        expect(createButton.attributes().disabled).toBe(undefined);
    });

    it('should not be able to add properties in filled state', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setData({ searchTerm: 'Size' });

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
