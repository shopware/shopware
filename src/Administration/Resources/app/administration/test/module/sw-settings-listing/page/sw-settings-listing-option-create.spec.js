import { shallowMount } from '@vue/test-utils';

import 'src/module/sw-settings-listing/page/sw-settings-listing-option-base';
import 'src/module/sw-settings-listing/page/sw-settings-listing-option-create';

describe('src/module/sw-setttigs-listing/page/sw-settings-listing-option-create', () => {
    function getProductSortingEntity() {
        return {
            locked: false,
            key: 'asasdsafsdfsdafsdafasdf',
            position: 1,
            active: true,
            fields: [
                {
                    field: 'product.listingPrices',
                    order: 'desc',
                    position: 0,
                    naturalSorting: 0
                },
                {
                    field: 'product.listingPrices',
                    order: 'desc',
                    position: 0,
                    naturalSorting: 0
                },
                {
                    field: 'my_first_custom_field',
                    order: 'desc',
                    position: 0,
                    naturalSorting: 0
                }
            ],
            label: 'asasdsafsdfsdafsdafasdf',
            createdAt: '2020-08-06T13:06:03.799+00:00',
            updatedAt: null,
            translated: {
                label: 'asasdsafsdfsdafsdafasdf'
            },
            apiAlias: null,
            id: '481a3502b72c4fd99b693c7998b93e37',
            translations: []
        };
    }

    function createWrapper() {
        return shallowMount(Shopware.Component.build('sw-settings-listing-option-create'), {
            mocks: {
                $tc: translationKey => translationKey,
                $t: translationKey => translationKey,
                $router: {}
            },
            provide: {
                next5983: true,
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.resolve(),
                        create: () => Promise.resolve(getProductSortingEntity()),
                        save: () => Promise.resolve({ config: { data: JSON.stringify({ id: 'asdfaf' }) } })
                    })
                },
                systemConfigApiService: {}
            },
            stubs: {
                'sw-page': {
                    template: '<div></div>'
                }
            }
        });
    }

    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should create a product sorting entity', async () => {
        const productSortingEntity = wrapper.vm.productSortingEntity;

        expect(productSortingEntity).resolves.toEqual({
            active: true,
            apiAlias: null,
            createdAt: '2020-08-06T13:06:03.799+00:00',
            fields: [
                {
                    field: 'product.listingPrices',
                    naturalSorting: 0,
                    order: 'desc',
                    position: 0
                },
                {
                    field: 'product.listingPrices',
                    naturalSorting: 0,
                    order: 'desc',
                    position: 0
                },
                {
                    field: 'my_first_custom_field',
                    naturalSorting: 0,
                    order: 'desc',
                    position: 0
                }
            ],
            id: '481a3502b72c4fd99b693c7998b93e37',
            key: 'asasdsafsdfsdafsdafasdf',
            label: 'asasdsafsdfsdafsdafasdf',
            locked: false,
            position: 1,
            translated: { label: 'asasdsafsdfsdafsdafasdf' },
            translations: [],
            updatedAt: null
        });
    });

    it('should throw an success message when saving', async () => {
        wrapper.vm.$router.push = jest.fn();

        await wrapper.vm.onSave();

        expect(wrapper.vm.$router.push).toHaveBeenCalled();
    });

    it('should throw an error message when the product sorting entity couldn\'t be saved', async () => {
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.onSave();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalled();
    });

    it('should display the entity name for the smart bar heading', async () => {
        wrapper.vm.productSortingEntity.label = 'label';

        expect(wrapper.vm.smartBarHeading).toBe('label');
    });

    it('should display the fallback snippet for the smart bar heading', async () => {
        expect(wrapper.vm.smartBarHeading).toBe('sw-settings-listing.create.smartBarTitle');
    });
});
