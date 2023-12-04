import { shallowMount } from '@vue/test-utils_v2';
import swFirstRunWizardPlugins from 'src/module/sw-first-run-wizard/view/sw-first-run-wizard-plugins';
import 'src/app/component/base/sw-label';

Shopware.Component.register('sw-first-run-wizard-plugins', swFirstRunWizardPlugins);

/**
 * @package services-settings
 */
describe('src/module/sw-first-run-wizard/view/sw-first-run-wizard-plugins', () => {
    /** @type Wrapper */
    let wrapper;

    async function createWrapper() {
        return shallowMount(await Shopware.Component.build('sw-first-run-wizard-plugins'), {
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
            stubs: {
                'sw-loader': true,
                'sw-container': {
                    template: '<div><slot></slot></div>',
                },
                'sw-label': await Shopware.Component.build('sw-label'),
                'sw-plugin-card': true,
            },
        });
    }

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have the right amount of region labels', async () => {
        wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const amountOfRegionLabels = wrapper.findAll('.sw-label-region').length;
        expect(amountOfRegionLabels).toBe(3);
    });

    it('should show category labels when clicking on a region label', async () => {
        wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        // there should not be a single category label before clicking on a region label
        const amountOfCategoryLabelsBeforeClick = wrapper.findAll('.sw-label-category').length;
        expect(amountOfCategoryLabelsBeforeClick).toBe(0);

        /** @type Wrapper */
        const regionLabel = wrapper.find('.sw-label-region');
        await regionLabel.trigger('click');

        const allCategoryLabels = wrapper.findAll('.sw-label-category');
        expect(allCategoryLabels).toHaveLength(1);
    });

    it('should show plugins when clicking on a category label', async () => {
        wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        /** @type Wrapper */
        const regionLabel = wrapper.find('.sw-label-region');
        await regionLabel.trigger('click');

        // there should not be a single plugin before clicking on a category label
        const amountOfPluginCardsBeforeClick = wrapper.findAll('sw-plugin-card-stub').length;
        expect(amountOfPluginCardsBeforeClick).toBe(0);

        /** @type Wrapper */
        const categoryLabel = wrapper.find('.sw-label-category');
        await categoryLabel.trigger('click');
        await wrapper.vm.$nextTick();

        const amountOfPluginCards = wrapper.findAll('sw-plugin-card-stub').length;
        expect(amountOfPluginCards).toBe(1);
    });
});
