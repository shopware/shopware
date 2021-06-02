import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-product/component/sw-product-add-properties-modal';

const propertiesMock = [
    {
        id: 'colorId',
        name: 'color',
        translated: {
            name: 'color'
        },
        options: {
            source: '/property-group/colorId/options',
            entity: 'property_group_option'
        }
    },
    {
        id: 'sizeId',
        name: 'size',
        translated: {
            name: 'size'
        },
        options: {
            source: '/property-group/sizeId/options',
            entity: 'property_group_option'
        }
    }
];
propertiesMock.total = 2;

const propertyValuesMock = [
    {
        groupId: 'colorId',
        id: '01',
        name: 'red',
        translated: {
            name: 'red'
        }
    },
    {
        groupId: 'colorId',
        id: '02',
        name: 'blue',
        translated: {
            name: 'blue'
        }
    },
    {
        groupId: 'colorId',
        id: '03',
        name: 'green',
        translated: {
            name: 'green'
        }
    }
];
propertyValuesMock.total = 3;

const newPropertiesMock = [
    {
        property: {
            groupId: 'colorId',
            id: '101',
            name: 'white',
            translated: {
                name: 'white'
            }
        },
        selected: true
    },
    {
        property: {
            groupId: 'colorId',
            id: '102',
            name: 'black',
            translated: {
                name: 'black'
            }
        },
        selected: true
    },
    {
        property: {
            groupId: 'sizeId',
            id: '103',
            name: '30',
            translated: {
                name: '30'
            }
        },
        selected: true
    },
    {
        property: {
            groupId: 'sizeId',
            id: '104',
            name: '32',
            translated: {
                name: '32'
            }
        },
        selected: true
    }
];

const $refsMock = {
    propertiesListing: {
        selectAll: () => {},
        selectItem: () => {}
    },
    propertyValuesListing: {
        selectAll: () => {},
        selectItem: () => {}
    }
};

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-product-add-properties-modal'), {
        localVue,
        stubs: {
            'sw-modal': true,
            'sw-container': true,
            'sw-card-section': true,
            'sw-grid': true,
            'sw-empty-state': true,
            'sw-simple-search-field': true,
            'sw-pagination': true,
            'sw-loader': true,
            'sw-button': true
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => {
                        return Promise.resolve();
                    }
                })
            }
        },
        propsData: {
            newProperties: []
        }
    });
}

describe('src/module/sw-product/component/sw-product-add-properties-modal', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.JS component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should get properties when component got created', () => {
        wrapper.vm.getProperties = jest.fn();

        wrapper.vm.createdComponent();

        expect(wrapper.vm.getProperties).toHaveBeenCalledTimes(1);
        wrapper.vm.getProperties.mockRestore();
    });

    it('should get properties successful', async () => {
        wrapper.vm.propertyGroupRepository.search = jest.fn(() => {
            return Promise.resolve(propertiesMock);
        });
        wrapper.vm.setSelectedPropertiesCount = jest.fn();

        await wrapper.vm.getProperties();

        expect(wrapper.vm.properties).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    id: 'colorId',
                    name: 'color'
                }),
                expect.objectContaining({
                    id: 'sizeId',
                    name: 'size'
                })
            ])
        );
        expect(wrapper.vm.propertiesTotal).toBe(2);
        expect(wrapper.vm.setSelectedPropertiesCount).toHaveBeenCalledTimes(1);
        wrapper.vm.propertyGroupRepository.search.mockRestore();
        wrapper.vm.setSelectedPropertiesCount.mockRestore();
    });

    it('should get properties failed', async () => {
        wrapper.vm.propertyGroupRepository.search = jest.fn(() => {
            return Promise.reject();
        });

        await wrapper.vm.getProperties();

        expect(wrapper.vm.properties).toEqual(
            expect.arrayContaining([])
        );
        wrapper.vm.propertyGroupRepository.search.mockRestore();
    });

    it('should set selected properties count successful', async () => {
        await wrapper.setData({
            properties: propertiesMock
        });
        await wrapper.setProps({
            newProperties: newPropertiesMock
        });

        wrapper.vm.setSelectedPropertiesCount();

        expect(wrapper.vm.properties).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    id: 'colorId',
                    name: 'color',
                    selectedPropertiesCount: 2
                }),
                expect.objectContaining({
                    id: 'sizeId',
                    name: 'size',
                    selectedPropertiesCount: 2
                })
            ])
        );
    });

    it('should get property values when selecting property', async () => {
        wrapper.vm.getPropertyValues = jest.fn(() => {
            return Promise.resolve();
        });

        await wrapper.setData({
            $refs: $refsMock
        });

        wrapper.vm.onSelectProperty(propertiesMock[0]);

        expect(wrapper.vm.selectedProperty).toEqual(
            expect.objectContaining({
                id: 'colorId',
                name: 'color'
            })
        );
        expect(wrapper.vm.propertyValuesPage).toBe(1);
        expect(wrapper.vm.getPropertyValues).toHaveBeenCalledTimes(1);
        wrapper.vm.getPropertyValues.mockRestore();
    });

    it('should get property values successful', async () => {
        await wrapper.setData({
            $refs: $refsMock,
            selectedProperty: propertiesMock[0]
        });
        await wrapper.setProps({
            newProperties: newPropertiesMock
        });

        wrapper.vm.$refs.propertyValuesListing.selectItem = jest.fn();
        wrapper.vm.propertyGroupOptionRepository.search = jest.fn(() => {
            return Promise.resolve(propertyValuesMock);
        });

        await wrapper.vm.getPropertyValues();

        expect(wrapper.vm.propertyValues).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    groupId: 'colorId',
                    id: '01',
                    name: 'red'
                }),
                expect.objectContaining({
                    groupId: 'colorId',
                    id: '02',
                    name: 'blue'
                }),
                expect.objectContaining({
                    groupId: 'colorId',
                    id: '03',
                    name: 'green'
                })
            ])
        );
        expect(wrapper.vm.propertyValuesTotal).toBe(3);
        expect(wrapper.vm.$refs.propertyValuesListing.selectItem).toHaveBeenCalledTimes(4);
        wrapper.vm.propertyGroupOptionRepository.search.mockRestore();
        wrapper.vm.$refs.propertyValuesListing.selectItem.mockRestore();
    });

    it('should get property values failed', async () => {
        await wrapper.setData({
            selectedProperty: propertiesMock[0]
        });

        wrapper.vm.propertyGroupOptionRepository.search = jest.fn(() => {
            return Promise.reject();
        });

        await wrapper.vm.getPropertyValues();

        expect(wrapper.vm.propertyValues).toEqual(
            expect.arrayContaining([])
        );
        wrapper.vm.propertyGroupOptionRepository.search.mockRestore();
    });

    it('should emit no event when selecting property value', async () => {
        await wrapper.setData({
            isSelectable: false
        });

        wrapper.vm.onSelectPropertyValue({}, propertyValuesMock[0], true);

        const emitted = wrapper.emitted();
        expect(emitted).toEqual(expect.objectContaining({}));
    });

    it('should emit an adding event when selecting property value', async () => {
        await wrapper.setData({
            isSelectable: true
        });
        await wrapper.setProps({
            newProperties: newPropertiesMock
        });

        wrapper.vm.onSelectPropertyValue({}, propertyValuesMock[0], true);

        const emitted = wrapper.emitted()['add-new-properties-item'];
        expect(emitted).toBeTruthy();
    });

    it('should emit an updating event when selecting property value', async () => {
        await wrapper.setData({
            isSelectable: true
        });
        await wrapper.setProps({
            newProperties: newPropertiesMock
        });

        wrapper.vm.onSelectPropertyValue({}, newPropertiesMock[0].property, false);

        const emitted = wrapper.emitted()['update-new-properties-item'];
        expect(emitted).toBeTruthy();
    });

    it('should get properties when changing properties page', () => {
        wrapper.vm.getProperties = jest.fn(() => {
            return Promise.resolve();
        });

        wrapper.vm.onChangePageProperties({ page: 2, limit: 10 });

        expect(wrapper.vm.propertiesPage).toBe(2);
        expect(wrapper.vm.propertiesLimit).toBe(10);
        expect(wrapper.vm.getProperties).toHaveBeenCalledTimes(1);
        wrapper.vm.getProperties.mockRestore();
    });

    it('should get property values when changing property page', async () => {
        wrapper.vm.getPropertyValues = jest.fn(() => {
            return Promise.resolve();
        });

        wrapper.vm.onChangePagePropertyValues({ page: 2, limit: 10 });

        expect(wrapper.vm.propertyValuesPage).toBe(2);
        expect(wrapper.vm.propertyValuesLimit).toBe(10);
        expect(wrapper.vm.getPropertyValues).toHaveBeenCalledTimes(1);
        wrapper.vm.getPropertyValues.mockRestore();
    });

    it('should get properties when changing search term', async () => {
        await wrapper.setData({
            $refs: $refsMock,
            selectedProperty: {}
        });

        wrapper.vm.$refs.propertiesListing.selectItem = jest.fn();
        wrapper.vm.getProperties = jest.fn(() => {
            return Promise.resolve();
        });

        wrapper.vm.onChangeSearchTerm('Size');

        expect(wrapper.vm.$refs.propertiesListing.selectItem).toHaveBeenCalledWith(false, {});
        expect(wrapper.vm.selectedProperty).toBe(null);
        expect(wrapper.vm.searchTerm).toBe('Size');
        expect(wrapper.vm.propertyGroupCriteria.term).toBe('Size');
        wrapper.vm.getProperties.mockRestore();
    });

    it('should emit an event when pressing on cancel button', () => {
        wrapper.vm.onCancel();

        const emitted = wrapper.emitted()['modal-cancel'];
        expect(emitted).toBeTruthy();
    });

    it('should emit an event when pressing on save button', async () => {
        wrapper.vm.onSave();

        const emitted = wrapper.emitted()['modal-save'];
        expect(emitted).toBeTruthy();
    });
});
