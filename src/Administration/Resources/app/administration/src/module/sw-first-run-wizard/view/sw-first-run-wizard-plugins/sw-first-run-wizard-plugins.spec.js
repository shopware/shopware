import { mount } from '@vue/test-utils';

/**
 * @package checkout
 */
describe('src/module/sw-first-run-wizard/view/sw-first-run-wizard-plugins', () => {
    async function createWrapper() {
        return mount(
            await wrapTestComponent('sw-first-run-wizard-plugins', {
                sync: true,
            }),
            {
                global: {
                    stubs: {
                        'sw-label': await wrapTestComponent('sw-label'),
                        'sw-container': {
                            template: '<div><slot></slot></div>',
                        },
                        'sw-plugin-card': true,
                        'sw-loader': true,
                        'sw-color-badge': true,
                        'sw-icon': true,
                    },
                    provide: {
                        recommendationsService: {
                            getRecommendationRegions() {
                                return Promise.resolve({
                                    items: [
                                        {
                                            name: 'asia',
                                            label: 'Asia',
                                            categories: [
                                                {
                                                    name: 'payment',
                                                    label: 'Payment',
                                                },
                                            ],
                                        },
                                        {
                                            name: 'europe',
                                            label: 'Europe',
                                            categories: [
                                                {
                                                    name: 'shipping',
                                                    label: 'Shipping',
                                                },
                                            ],
                                        },
                                        {
                                            name: 'oceania',
                                            label: 'Oceania',
                                            categories: [
                                                {
                                                    name: 'other',
                                                    label: 'Other',
                                                },
                                            ],
                                        },
                                    ],
                                });
                            },
                            getRecommendations() {
                                return Promise.resolve({
                                    items: [
                                        {
                                            isCategoryLead: true,
                                            name: 'payment-provider',
                                            iconPath: '',
                                            label: 'Payment provider',
                                            manufacturer: 'Jon Doe Company',
                                            shortDescription: 'Lorem ipsum',
                                        },
                                    ],
                                });
                            },
                        },
                    },
                },
            },
        );
    }

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have the right amount of region labels', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const amountOfRegionLabels = wrapper.findAll('.sw-label-region').length;
        expect(amountOfRegionLabels).toBe(3);
    });

    it('should show category labels when clicking on a region label', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        // there should not be a single category label before clicking on a region label
        const amountOfCategoryLabelsBeforeClick = wrapper.findAll('.sw-label-category').length;
        expect(amountOfCategoryLabelsBeforeClick).toBe(0);

        const regionLabel = wrapper.find('.sw-label-region');
        await regionLabel.trigger('click');

        const allCategoryLabels = wrapper.findAll('.sw-label-category');
        expect(allCategoryLabels).toHaveLength(1);
    });

    it('should show plugins when clicking on a category label', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const regionLabel = wrapper.find('.sw-label-region');
        await regionLabel.trigger('click');

        // there should not be a single plugin before clicking on a category label
        const amountOfPluginCardsBeforeClick = wrapper.findAll('sw-plugin-card-stub').length;
        expect(amountOfPluginCardsBeforeClick).toBe(0);

        const categoryLabel = wrapper.find('.sw-label-category');
        await categoryLabel.trigger('click');
        await flushPromises();

        const amountOfPluginCards = wrapper.findAll('sw-plugin-card-stub').length;
        expect(amountOfPluginCards).toBe(1);
    });
});
