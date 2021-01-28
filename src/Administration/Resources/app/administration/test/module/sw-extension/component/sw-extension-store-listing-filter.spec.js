import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-extension/component/sw-extension-store-listing-filter';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-extension-store-listing-filter'), {
        mocks: {
            $tc: v => v
        },
        stubs: {
            'sw-loader': true,
            'sw-meteor-single-select': {
                props: ['options'],
                template: '<div class="sw-meteor-single-select"></div>'
            }
        },
        provide: {
            extensionStoreDataService: {
                listingFilters: () => {
                    return Promise.resolve({
                        filter: [
                            {
                                type: 'category',
                                name: 'category',
                                label: 'Category',
                                position: 1,
                                options: [
                                    {
                                        name: 'category',
                                        value: 'TempPromotion',
                                        label: 'Sale',
                                        position: 0,
                                        parent: null
                                    },
                                    {
                                        name: 'category',
                                        value: 'TempPromotion_Misc',
                                        label: 'Other',
                                        position: 0,
                                        parent: 'TempPromotion'
                                    },
                                    {
                                        name: 'category',
                                        value: 'TempPromotion_Themes',
                                        label: 'Themes',
                                        position: 0,
                                        parent: 'TempPromotion'
                                    },
                                    {
                                        name: 'category',
                                        value: 'TempPromotion_SEOOptimierung',
                                        label: 'SEO Optimization',
                                        position: 0,
                                        parent: 'TempPromotion'
                                    },
                                    {
                                        name: 'category',
                                        value: 'TempPromotion_Bestellprozess',
                                        label: 'Checkout process',
                                        position: 0,
                                        parent: 'TempPromotion'
                                    },
                                    {
                                        name: 'category',
                                        value: 'TempPromotion_KundenkontoPersonalisierung',
                                        label: 'Customer account + personalization',
                                        position: 0,
                                        parent: 'TempPromotion'
                                    },
                                    {
                                        name: 'category',
                                        value: 'TempPromotion_ConversionOptimierung',
                                        label: 'Conversion Optimization',
                                        position: 0,
                                        parent: 'TempPromotion'
                                    },
                                    {
                                        name: 'category',
                                        value: 'TempPromotion_B2BExtensions',
                                        label: 'B2B extensions',
                                        position: 0,
                                        parent: 'TempPromotion'
                                    },
                                    {
                                        name: 'category',
                                        value: 'Extensions',
                                        label: 'Extensions',
                                        position: 0,
                                        parent: null
                                    },
                                    {
                                        name: 'category',
                                        value: 'ValentinesDaySale',
                                        label: "Valentine's Day Sale",
                                        position: 0,
                                        parent: 'Extensions'
                                    },
                                    {
                                        name: 'category',
                                        value: 'Covid-19-PluginsForSupport',
                                        label: 'Covid-19 - Plugins for support',
                                        position: 0,
                                        parent: 'Extensions'
                                    },
                                    {
                                        name: 'category',
                                        value: 'Integration',
                                        label: 'Integration',
                                        position: 0,
                                        parent: 'Extensions'
                                    },
                                    {
                                        name: 'category',
                                        value: 'StorefrontDetailanpassungen',
                                        label: 'Frontend / detail adjustment',
                                        position: 0,
                                        parent: 'Extensions'
                                    },
                                    {
                                        name: 'category',
                                        value: 'GitHub',
                                        label: 'GitHub',
                                        position: 0,
                                        parent: 'Extensions'
                                    },
                                    {
                                        name: 'category',
                                        value: 'TheBestSEOTools',
                                        label: 'The best SEO tools',
                                        position: 0,
                                        parent: 'Extensions'
                                    },
                                    {
                                        name: 'category',
                                        value: 'B2BExtensions',
                                        label: 'B2B extensions',
                                        position: 0,
                                        parent: 'Extensions'
                                    },
                                    {
                                        name: 'category',
                                        value: 'MarketingTools',
                                        label: 'Marketing-Tools',
                                        position: 0,
                                        parent: 'Extensions'
                                    },
                                    {
                                        name: 'category',
                                        value: 'ConversionOptimierung',
                                        label: 'Conversion Optimization',
                                        position: 0,
                                        parent: 'Extensions'
                                    },
                                    {
                                        name: 'category',
                                        value: 'Einkaufswelten',
                                        label: 'Shopping Experiences',
                                        position: 0,
                                        parent: 'Extensions'
                                    },
                                    {
                                        name: 'category',
                                        value: 'MigrationTools',
                                        label: 'Migration tools',
                                        position: 0,
                                        parent: 'Extensions'
                                    },
                                    {
                                        name: 'category',
                                        value: 'KundenkontoPersonalisierung',
                                        label: 'Customer account + personalization',
                                        position: 0,
                                        parent: 'Extensions'
                                    },
                                    {
                                        name: 'category',
                                        value: 'Bestellprozess',
                                        label: 'Checkout process',
                                        position: 0,
                                        parent: 'Extensions'
                                    },
                                    {
                                        name: 'category',
                                        value: 'Sprache',
                                        label: 'Language & Internationalisation',
                                        position: 0,
                                        parent: 'Extensions'
                                    },
                                    {
                                        name: 'category',
                                        value: 'PreissuchmaschinenPortale',
                                        label: 'Price search engine / portal',
                                        position: 0,
                                        parent: 'Extensions'
                                    },
                                    {
                                        name: 'category',
                                        value: 'Auswertung',
                                        label: 'Evaluation and Analysis',
                                        position: 0,
                                        parent: 'Extensions'
                                    },
                                    {
                                        name: 'category',
                                        value: 'Administration',
                                        label: 'Administration',
                                        position: 0,
                                        parent: 'Extensions'
                                    },
                                    {
                                        name: 'category',
                                        value: 'SEOOptimierung',
                                        label: 'SEO Optimization',
                                        position: 0,
                                        parent: 'Extensions'
                                    },
                                    {
                                        name: 'category',
                                        value: 'ProductLicense',
                                        label: 'Licenses',
                                        position: 0,
                                        parent: null
                                    },
                                    {
                                        name: 'category',
                                        value: 'Themes',
                                        label: 'Themes',
                                        position: 0,
                                        parent: null
                                    },
                                    {
                                        name: 'category',
                                        value: 'Branche',
                                        label: 'Industry',
                                        position: 0,
                                        parent: 'Themes'
                                    }
                                ]
                            },
                            {
                                type: 'rating',
                                name: 'rating',
                                label: 'Rating',
                                position: 2,
                                options: [
                                    { name: 'rating', value: '5', label: 'Min 5 stars', position: 1 },
                                    { name: 'rating', value: '4', label: 'Min 4 stars', position: 2 },
                                    { name: 'rating', value: '3', label: 'Min 3 stars', position: 3 },
                                    { name: 'rating', value: '2', label: 'Min 2 stars', position: 4 },
                                    { name: 'rating', value: '1', label: 'Min 1 star', position: 5 }
                                ]
                            },
                            {
                                type: 'multi-select',
                                name: 'certification',
                                label: 'Certification',
                                position: 3,
                                options: [
                                    { name: 'certification', value: 'gold', label: 'Gold', position: 1 },
                                    { name: 'certification', value: 'silver', label: 'Silver', position: 2 },
                                    { name: 'certification', value: 'bronze', label: 'Bronze', position: 3 }
                                ]
                            },
                            {
                                type: 'multi-select',
                                name: 'variants',
                                label: 'Payment',
                                position: 4,
                                options: [
                                    { name: 'variants', value: 'buy', label: 'Buy', position: 1 },
                                    { name: 'variants', value: 'rent', label: 'Rent', position: 2 },
                                    { name: 'variants', value: 'free', label: 'Free', position: 3 }
                                ]
                            },
                            {
                                type: 'multi-select',
                                name: 'other',
                                label: 'Other',
                                position: 5,
                                options: [
                                    { name: 'support', value: '1', label: 'Includes Support', position: 1 },
                                    { name: 'test', value: '1', label: 'Includes Support', position: 2 }
                                ]
                            }
                        ],
                        sorting: {
                            default: {
                                orderBy: 'popularity',
                                orderSequence: 'desc',
                                label: 'Popularity',
                                position: 5
                            },
                            options: [
                                {
                                    orderBy: 'name',
                                    orderSequence: 'asc',
                                    label: 'Name A-Z',
                                    position: 1,
                                    orderIdentifier: 'name##asc'
                                },
                                {
                                    orderBy: 'name',
                                    orderSequence: 'desc',
                                    label: 'Name Z-A',
                                    position: 2,
                                    orderIdentifier: 'name##desc'
                                },
                                {
                                    orderBy: 'releaseDate',
                                    orderSequence: 'desc',
                                    label: 'Release date',
                                    position: 3,
                                    orderIdentifier: 'releaseDate##desc'
                                },
                                {
                                    orderBy: 'rating',
                                    orderSequence: 'desc',
                                    label: 'Rating',
                                    position: 4,
                                    orderIdentifier: 'rating##desc'
                                },
                                {
                                    orderBy: 'popularity',
                                    orderSequence: 'desc',
                                    label: 'Popularity',
                                    position: 5,
                                    orderIdentifier: 'popularity##desc'
                                }
                            ]
                        }
                    });
                }
            }
        }
    });
}

describe('src/module/sw-extension/component/sw-extension-store-listing-filter', () => {
    /** @type Wrapper */
    let wrapper;

    beforeAll(() => {
        Shopware.State.registerModule('shopwareExtensions', {
            namespaced: true,
            state: {
                search: {
                    page: 1,
                    limit: 12,
                    rating: null,
                    sorting: null,
                    term: null,
                    filter: {}
                }
            }
        });
    });

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the sorting', async () => {
        const sorting = wrapper.find('.sw-extension-store-listing-filter__sorting');

        const options = wrapper.vm.sortingOptions;
        const propOptions = sorting.props('options');
        expect(propOptions).toEqual(options);

        propOptions.forEach(option => {
            const orderIdentifierExpect = `${option.orderBy}##${option.orderSequence}`;
            expect(option.orderIdentifier).toEqual(orderIdentifierExpect);
        });
    });

    it('should render a meteor single select for each filter', async () => {
        const filters = wrapper.findAll('.sw-extension-store-listing-filter__filters');

        expect(filters.length).toBe(5);

        wrapper.vm.listingFiltersSorted.forEach((filter, index) => {
            const selectFilter = filters.at(index);

            expect(selectFilter.props('options')).toEqual([
                {
                    label: 'sw-extension.store.listing.anyOption',
                    value: null
                },
                ...filter.options
            ]);
        });
    });
});
