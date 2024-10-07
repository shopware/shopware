import { mount } from '@vue/test-utils';

/**
 * @package inventory
 */
describe('src/module/sw-settings-listing/page/sw-settings-listing-option-base', () => {
    function getProductSortings() {
        return [
            {
                label: 'Price descending',
                id: '2e55a50661ce4f42b188996aebbf6117',
                key: 'price-desc',
                fields: [
                    {
                        field: 'product.cheapestPrice',
                        order: 'desc',
                        position: 0,
                        naturalSorting: 0,
                    },
                    {
                        field: 'product.cheapestPrice',
                        order: 'desc',
                        position: 0,
                        naturalSorting: 0,
                    },
                    {
                        field: 'my_first_custom_field',
                        order: 'desc',
                        position: 0,
                        naturalSorting: 0,
                    },
                ],
            },
            {
                label: 'Price ascending',
                id: '2e55a50661ce4f42b188996aebbf6118',
                key: 'price-asc',
                fields: [
                    {
                        field: 'product.cheapestPrice',
                        order: 'desc',
                        position: 0,
                        naturalSorting: 0,
                    },
                ],
            },
        ];
    }

    function getDefaultSortingId() {
        return '2e55a50661ce4f42b188996aebbf6117';
    }

    function getProductSortingEntityWithoutCriteria() {
        const productSortingEntity = getProductSortings()[0];
        productSortingEntity.fields = [];

        return productSortingEntity;
    }

    function getProductSortingEntityWithoutName() {
        const productSortingEntity = getProductSortings()[0];
        productSortingEntity.label = null;

        return productSortingEntity;
    }

    function getCustomFields() {
        return [
            {
                name: 'my_first_custom_field',
                id: '4aab5584aaa948eb833451390bfe374a',
            },
        ];
    }

    async function createWrapper() {
        return mount(
            await wrapTestComponent('sw-settings-listing-option-base', {
                sync: true,
            }),
            {
                global: {
                    renderStubDefaultSlot: true,
                    mocks: {
                        $route: {
                            params: {
                                id: getProductSortings()[0].id,
                            },
                        },
                    },
                    provide: {
                        repositoryFactory: {
                            create: (repository) => {
                                if (repository === 'custom_field') {
                                    return {
                                        search: () => Promise.resolve(getCustomFields()),
                                    };
                                }

                                return {
                                    get: (id) => {
                                        let response = null;

                                        getProductSortings().forEach((element) => {
                                            if (element.id === id) {
                                                response = element;
                                            }
                                        });

                                        return Promise.resolve(response);
                                    },
                                    search: (param) => {
                                        let response = null;

                                        getProductSortings().forEach((element) => {
                                            if (element[param.filters[0].field] === param.filters[0].value) {
                                                response = element;
                                            }
                                        });

                                        return Promise.resolve({
                                            first: () => {
                                                return response;
                                            },
                                        });
                                    },
                                    save: (entity) => {
                                        if (entity.fail) {
                                            return Promise.reject();
                                        }

                                        return Promise.resolve();
                                    },
                                };
                            },
                        },
                        systemConfigApiService: {
                            getValues: () => {
                                return Promise.resolve({
                                    'core.listing.defaultSorting': getDefaultSortingId(),
                                });
                            },
                        },
                    },
                    stubs: {
                        'sw-page': true,
                        'sw-button': true,
                        'sw-language-switch': true,
                        'sw-settings-listing-option-general-info': true,
                        'sw-settings-listing-option-criteria-grid': true,
                        'sw-settings-listing-delete-modal': true,
                    },
                },
            },
        );
    }

    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should disable the save button if no criteria exists', async () => {
        await wrapper.setData({
            productSortingEntity: getProductSortingEntityWithoutCriteria(),
        });

        const isSaveButtonDisabled = wrapper.vm.isSaveButtonDisabled;

        expect(isSaveButtonDisabled).toBe(true);
    });

    it('should enable the save button if at least one criteria exists', async () => {
        const isSaveButtonDisabled = wrapper.vm.isSaveButtonDisabled;

        expect(isSaveButtonDisabled).toBe(false);
    });

    it('should display the entity name when the entity has a name', async () => {
        const displayValue = wrapper.vm.smartBarHeading;

        expect(displayValue).toBe('Price descending');
    });

    it('should display the fallback snippet when the entity has no name', async () => {
        await wrapper.setData({
            productSortingEntity: getProductSortingEntityWithoutName(),
        });

        const displayValue = wrapper.vm.smartBarHeading;

        expect(displayValue).toBe('sw-settings-listing.base.smartBarTitle');
    });

    it('should get the correct entity id from the route', async () => {
        const idOfProductSortingEntity = wrapper.vm.getProductSortingEntityId();

        expect(idOfProductSortingEntity).toBe('2e55a50661ce4f42b188996aebbf6117');
    });

    it('should return true if criteria is a custom field', async () => {
        const isCriteriaACustomField = wrapper.vm.isCriteriaACustomField('my_first_custom_field');

        expect(isCriteriaACustomField).toBe(true);
    });

    it('should return false if criteria is not a custom field', async () => {
        const isCriteriaACustomField = wrapper.vm.isCriteriaACustomField('non_existing_custom_field');

        expect(isCriteriaACustomField).toBe(false);
    });

    it('should transform custom field criterias', async () => {
        wrapper.vm.transformCustomFieldCriterias();
        const transformedCustomFieldCriterias = wrapper.vm.productSortingEntity.fields;

        expect(transformedCustomFieldCriterias).toEqual([
            {
                field: 'product.cheapestPrice',
                naturalSorting: 0,
                order: 'desc',
                position: 0,
            },
            {
                field: 'product.cheapestPrice',
                naturalSorting: 0,
                order: 'desc',
                position: 0,
            },
            {
                field: 'customFields.my_first_custom_field',
                naturalSorting: 0,
                order: 'desc',
                position: 0,
            },
        ]);
    });

    it('should throw an success notification when saving custom fields', async () => {
        // mock notification function
        wrapper.vm.createNotificationSuccess = jest.fn();

        await wrapper.vm.onSave();

        expect(wrapper.vm.createNotificationSuccess).toHaveBeenCalled();
        wrapper.vm.createNotificationSuccess.mockRestore();
    });

    it('should throw an error message when saving goes wrong', async () => {
        // mock notification function
        wrapper.vm.createNotificationError = jest.fn();

        wrapper.vm.productSortingEntity.fail = true;

        await wrapper.vm.onSave();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalled();
        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should throw an error message when key already exists', async () => {
        // mock notification function
        wrapper.vm.createNotificationError = jest.fn();

        wrapper.vm.productSortingEntity.key = 'price-asc';

        await wrapper.vm.onSave();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalled();
        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should recognize the default sorting', async () => {
        expect(wrapper.vm.isDefaultSorting).toBeTruthy();
    });
});
