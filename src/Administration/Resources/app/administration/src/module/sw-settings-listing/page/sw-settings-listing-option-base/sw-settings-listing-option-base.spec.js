import { shallowMount } from '@vue/test-utils';

import swSettingsListingOptionBase from 'src/module/sw-settings-listing/page/sw-settings-listing-option-base';

Shopware.Component.register('sw-settings-listing-option-base', swSettingsListingOptionBase);

describe('src/module/sw-settings-listing/page/sw-settings-listing-option-base', () => {
    function getProductSortingEntity() {
        return {
            label: 'Price descending',
            id: '2e55a50661ce4f42b188996aebbf6117',
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
        };
    }

    function getDefaultSortingKey() {
        return 'name-desc';
    }

    function getProductSortingEntityWithoutCriteria() {
        const productSortingEntity = getProductSortingEntity();
        productSortingEntity.fields = [];

        return productSortingEntity;
    }

    function getProductSortingEntityWithoutName() {
        const productSortingEntity = getProductSortingEntity();
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
        return shallowMount(await Shopware.Component.build('sw-settings-listing-option-base'), {
            mocks: {
                $route: {
                    params: {
                        id: getProductSortingEntity().id,
                    },
                },
            },
            provide: {
                repositoryFactory: {
                    create: repository => {
                        if (repository === 'custom_field') {
                            return {
                                search: () => Promise.resolve(getCustomFields()),
                            };
                        }

                        return {
                            get: () => Promise.resolve(getProductSortingEntity()),
                            search: () => Promise.resolve(),
                            save: entity => {
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
                        return Promise.resolve(getDefaultSortingKey());
                    },
                },
            },
            stubs: {
                'sw-page': true,
                'sw-button': true,
                'sw-language-switch': true,
                'sw-settings-listing-option-general-info': true,
                'sw-settings-listing-option-criteria-grid': true,
            },
        });
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

        await wrapper.vm.onSave().catch(() => {});
        expect(wrapper.vm.createNotificationError).toHaveBeenCalled();

        wrapper.vm.createNotificationError.mockRestore();
    });
});
