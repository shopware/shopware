/*
 * @package inventory
 */

import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import swProductProperties from 'src/module/sw-product/component/sw-product-properties';
import 'src/app/component/utils/sw-inherit-wrapper';
import 'src/app/component/base/sw-card';

Shopware.Component.register('sw-product-properties', swProductProperties);

const { State } = Shopware;

let productPropertiesMock = [
    { id: '01', groupId: 'sizeId', name: '30' },
    { id: '02', groupId: 'sizeId', name: '32' },
    { id: '03', groupId: 'colorId', name: 'white' },
    { id: '04', groupId: 'colorId', name: 'black' },
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

const productMock = {
    id: 'productId',
    parentId: 'parentProductId',
    properties: [],
};

const parentProductMock = {
    id: 'parentProductId',
    parentId: null,
    properties: productPropertiesMock,
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
            'sw-container': {
                template: `
                    <div class="sw-container">
                        <slot></slot>
                    </div>
                `,
            },
            'sw-card-section': {
                template: `
                    <div class="sw-card-section">
                        <slot></slot>
                    </div>
                `,
            },
            'sw-entity-listing': {
                props: ['items'],
                methods: {
                    resetSelection: () => {},
                },
                template: `
                    <div class="sw-entity-listing" ref="entityListing">
                        <template v-for="item in items">
                            <slot name="actions" v-bind="{ item }"></slot>
                        </template>
                    </div>
                `,
            },
            'sw-empty-state': {
                template: `
                    <div class="sw-empty-state">
                        <slot></slot>
                        <slot name="actions"></slot>
                    </div>
                `,
            },
            'sw-product-add-properties-modal': true,
            'sw-loader': true,
            'sw-simple-search-field': true,
            'sw-button': true,
            'sw-icon': true,
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => {
                        return Promise.resolve({ total: 0 });
                    },
                }),
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                },
            },
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

    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should get group ids successful', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.$nextTick();
        await State.commit('swProductDetail/setProduct', productMock);
        await wrapper.vm.getGroupIds();

        expect(wrapper.vm.groupIds).toEqual(
            expect.arrayContaining(['sizeId', 'colorId']),
        );
    });

    it('should get group ids failed', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.$nextTick();
        await State.commit('swProductDetail/setProduct', {});
        await wrapper.vm.getGroupIds();

        expect(wrapper.vm.groupIds).toEqual(
            expect.arrayContaining([]),
        );
    });

    it('should get properties successful', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.propertyGroupRepository.search = jest.fn(() => {
            return Promise.resolve(propertiesMock);
        });

        await State.commit('swProductDetail/setProduct', productMock);
        await wrapper.vm.getGroupIds();
        wrapper.vm.getProperties();

        expect(wrapper.vm.properties).toEqual(
            expect.arrayContaining(propertiesMock),
        );
        wrapper.vm.propertyGroupRepository.search.mockRestore();
    });

    it('should get properties failed', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.propertyGroupRepository.search = jest.fn(() => {
            return Promise.reject();
        });

        await State.commit('swProductDetail/setProduct', productMock);
        await wrapper.vm.getGroupIds();
        wrapper.vm.getProperties();

        expect(wrapper.vm.properties).toEqual(
            expect.arrayContaining([]),
        );
        wrapper.vm.propertyGroupRepository.search.mockRestore();
    });

    it('should get properties failed if having no inputs', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.getProperties = jest.fn(() => {
            return Promise.reject(new Error('Whoops!'));
        });

        await State.commit('swProductDetail/setProduct', productMock);

        const getError = async () => {
            try {
                await wrapper.vm.getProperties();

                throw new Error('Method should have thrown an error');
            } catch (error) {
                return error;
            }
        };
        expect((await getError()).message).toBe('Whoops!');

        expect(wrapper.vm.properties).toEqual(
            expect.arrayContaining([]),
        );
        wrapper.vm.getProperties.mockRestore();
    });

    it('should delete property value successful', async () => {
        const wrapper = await createWrapper();
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
                expect.objectContaining({ id: '04', groupId: 'colorId', name: 'black' }),
            ]),
        );
        wrapper.vm.propertyGroupRepository.search.mockRestore();
    });

    it('should delete property successful', async () => {
        const wrapper = await createWrapper();
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
                expect.objectContaining({ id: '04', groupId: 'colorId', name: 'black' }),
            ]),
        );
        wrapper.vm.propertyGroupRepository.search.mockRestore();
    });

    it('should delete properties successful', async () => {
        const wrapper = await createWrapper();
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
                expect.objectContaining({ id: '02', groupId: 'sizeId', name: '32' }),
            ]),
        );
        wrapper.vm.propertyGroupRepository.search.mockRestore();
    });

    it('should get properties when changing search term', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        const error = new Error('Whoops!');
        wrapper.vm.getProperties = jest.fn(() => {
            return Promise.reject(error);
        });

        await expect(wrapper.vm.onChangeSearchTerm('textile')).rejects.toEqual(error);

        expect(wrapper.vm.searchTerm).toBe('textile');
        expect(wrapper.vm.propertyGroupCriteria.term).toBe('textile');
    });

    it('should turn on add properties modal', async () => {
        const wrapper = await createWrapper();
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
        await wrapper.vm.$nextTick();
        wrapper.vm.updateNewProperties = jest.fn();

        wrapper.vm.turnOffAddPropertiesModal();

        expect(wrapper.vm.showAddPropertiesModal).toBe(false);
        expect(wrapper.vm.updateNewProperties).toHaveBeenCalledTimes(1);
        wrapper.vm.updateNewProperties.mockRestore();
    });

    it('should update new properties correctly', async () => {
        const wrapper = await createWrapper();
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
        wrapper.vm.propertyGroupRepository.search.mockRestore();
    });

    it('should call a turning off modal function when canceling properties modal', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.turnOffAddPropertiesModal = jest.fn();

        wrapper.vm.onCancelAddPropertiesModal();

        expect(wrapper.vm.turnOffAddPropertiesModal).toHaveBeenCalledTimes(1);
        wrapper.vm.turnOffAddPropertiesModal.mockRestore();
    });

    it('should save add properties modal failed', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.turnOffAddPropertiesModal = jest.fn();

        wrapper.vm.onSaveAddPropertiesModal([]);

        expect(wrapper.vm.turnOffAddPropertiesModal).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.newProperties).toEqual(
            expect.arrayContaining([]),
        );
        wrapper.vm.turnOffAddPropertiesModal.mockRestore();
    });

    it('should be able to add properties in empty state', async () => {
        const wrapper = await createWrapper([
            'product.editor',
        ]);
        await wrapper.vm.$nextTick();

        await wrapper.setData({ properties: [], searchTerm: null });

        const createButton = wrapper.find('sw-button-stub');

        expect(createButton.attributes().disabled).toBeUndefined();
    });

    it('should not be able to add properties in empty state', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setData({ properties: [], searchTerm: null });

        const createButton = wrapper.find('sw-button-stub');
        expect(createButton.attributes().disabled).toBe('true');
    });

    it('should be able to add properties in filled state', async () => {
        const wrapper = await createWrapper([
            'product.editor',
        ]);

        await wrapper.vm.$nextTick();

        await wrapper.setData({ searchTerm: 'Size', properties: propertiesMock });

        const createButton = wrapper.find('sw-button-stub');

        expect(createButton.attributes().disabled).toBeUndefined();
    });

    it('should not be able to add properties in filled state', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setData({ searchTerm: 'Size', properties: propertiesMock });

        const createButton = wrapper.find('sw-button-stub');
        expect(createButton.attributes().disabled).toBe('true');
    });

    it('should be able to edit property', async () => {
        const wrapper = await createWrapper([
            'property.editor',
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
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.propertyGroupRepository.search = jest.fn(() => {
            return Promise.resolve(propertiesMock);
        });

        await State.commit('swProductDetail/setProduct', productMock);
        await wrapper.vm.getGroupIds();
        await wrapper.vm.getProperties();

        const entityListing = wrapper.find('.sw-product-properties__list');
        expect(entityListing.attributes()['allow-edit']).toBeUndefined();

        wrapper.vm.propertyGroupRepository.search.mockRestore();
    });

    it('should be able to delete property', async () => {
        const wrapper = await createWrapper([
            'product.deleter',
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
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.propertyGroupRepository.search = jest.fn(() => {
            return Promise.resolve(propertiesMock);
        });

        await State.commit('swProductDetail/setProduct', productMock);
        await wrapper.vm.getGroupIds();
        await wrapper.vm.getProperties();

        const entityListing = wrapper.find('.sw-product-properties__list');
        expect(entityListing.attributes()['allow-delete']).toBeUndefined();

        wrapper.vm.propertyGroupRepository.search.mockRestore();
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
