/**
 * @package system-settings
 */
import { mount } from '@vue/test-utils';

const salesChannels = [
    {
        name: 'Storefront',
        id: '7e0e4a256138402c82a20fcbb4fbb858',
    },
    {

        name: 'Headless',
        id: '98432def39fc4624b33213a56b8c944d',
    },
];

const mockResults = {
    nothing: {
        terms: 'nothing',
        result: {
            elements: [],
        },
    },
    oneResult: {
        terms: 'iron',
        result: {
            elements: [
                {
                    name: 'Durable Iron OpenDoor',
                    extensions: {
                        search: {
                            _score: 28799.999999,
                        },
                    },
                },
            ],
        },
    },
    multipleResults: {
        terms: 'awesome',
        result: {
            elements: [
                {
                    name: 'Awesome Copper Belly-flop Buffet',
                    extensions: {
                        search: {
                            _score: 40320,
                        },
                    },
                },
                {
                    name: 'Awesome Wooden Crystal Qlear',
                    extensions: {
                        search: {
                            _score: 34560,
                        },
                    },
                },
                {
                    name: 'Awesome Silk Ghost Voices',
                    extensions: {
                        search: {
                            _score: 34559.9999,
                        },
                    },
                },
            ],
        },
    },
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-settings-search-live-search', {
        sync: true,
    }), {
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-card': true,
                'sw-container': true,
                'sw-button': true,
                'sw-icon': true,
                'sw-field-error': true,
                'sw-simple-search-field': await wrapTestComponent('sw-simple-search-field'),
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-single-select': await wrapTestComponent('sw-single-select'),
                'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-popover': {
                    props: ['popoverClass'],
                    template: `
                    <div class="sw-popover" :class="popoverClass">
                        <slot></slot>
                    </div>`,
                },
                'sw-data-grid': await wrapTestComponent('sw-data-grid'),
                'sw-product-variant-info': await wrapTestComponent('sw-product-variant-info'),
                'sw-settings-search-live-search-keyword': await wrapTestComponent('sw-settings-search-live-search-keyword'),
            },

            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => {
                            return Promise.resolve(salesChannels);
                        },
                    }),
                },
                validationService: {},
                liveSearchService: {
                    search: jest.fn(({ terms }) => {
                        if (terms === mockResults.nothing.terms) {
                            return Promise.resolve(mockResults.nothing.result);
                        }

                        if (terms === mockResults.oneResult.terms) {
                            return Promise.resolve(mockResults.oneResult.result);
                        }

                        if (terms === mockResults.multipleResults.terms) {
                            return Promise.resolve(mockResults.multipleResults.result);
                        }

                        return Promise.resolve({});
                    }),
                },
            },
        },

        props: {
            currentSalesChannelId: null,
            searchTerms: '',
            searchResults: {},
        },
    });
}

describe('src/module/sw-settings-search/component/sw-settings-search-live-search', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the sales channel select', async () => {
        expect(wrapper.find('.sw-settings-search-live-search__sales-channel-select').exists()).toBeTruthy();
    });

    it('should show the search box disabled on no sales channel selected', async () => {
        const searchBox = wrapper.find('.sw-simple-search-field input');
        expect(searchBox.attributes().disabled).toBeDefined();
    });

    it('should enable the search box after set the sales channel id', async () => {
        const searchBox = wrapper.find('.sw-simple-search-field input');
        expect(searchBox.attributes().disabled).toBeDefined();

        const salesChannelSwitch = wrapper
            .find('.sw-settings-search-live-search__sales-channel-select .sw-select__selection');
        await salesChannelSwitch.trigger('click');
        await flushPromises();
        await wrapper.find('.sw-select-option--0').trigger('click');
        expect(searchBox.attributes().disabled).toBeFalsy();
    });

    it('should show no results message if search keywords is nothing', async () => {
        const salesChannelSwitch = wrapper
            .find('.sw-settings-search-live-search__sales-channel-select .sw-select__selection');
        await salesChannelSwitch.trigger('click');
        await flushPromises();
        await wrapper.find('.sw-select-option--0').trigger('click');
        await flushPromises();
        const searchBox = wrapper.find('.sw-simple-search-field input');
        await searchBox.setValue(mockResults.nothing.terms);
        await flushPromises();

        await searchBox.trigger('keypress', { key: 'Enter' });
        await flushPromises();

        await wrapper.setData({
            liveSearchResults: mockResults.nothing.result,
        });
        await flushPromises();
        const resultText = wrapper.find('.sw-settings-search-live-search__no-result');
        expect(resultText.text()).toBe('sw-settings-search.liveSearchTab.textNoResult');
        wrapper.vm.liveSearchService.search.mockReset();
    });

    it('should show one result for search', async () => {
        const salesChannelSwitch = wrapper
            .find('.sw-settings-search-live-search__sales-channel-select .sw-select__selection');
        await salesChannelSwitch.trigger('click');
        await flushPromises();
        await wrapper.find('.sw-select-option--0').trigger('click');
        await flushPromises();
        const searchBox = wrapper.find('.sw-simple-search-field input');
        await searchBox.setValue(mockResults.oneResult.terms);
        await flushPromises();

        await searchBox.trigger('keypress', { key: 'Enter' });
        await flushPromises();

        await wrapper.setData({
            liveSearchResults: mockResults.oneResult.result,
        });
        await flushPromises();

        const firstRow = wrapper.find('.sw-data-grid__row--0');
        expect(firstRow.find('.sw-product-variant-info').exists()).toBeTruthy();

        const scoreCell = firstRow.find('.sw-settings-search-live-search__grid-result__score');
        const scoreOrigin = mockResults.oneResult.result.elements[0].extensions.search._score;
        // The score should be round up
        expect(scoreCell.text()).toBe(Math.round(parseFloat(scoreOrigin)).toString());

        wrapper.vm.liveSearchService.search.mockReset();
    });

    it('should able to click on search glass to search', async () => {
        const salesChannelSwitch = wrapper
            .find('.sw-settings-search-live-search__sales-channel-select .sw-select__selection');
        await salesChannelSwitch.trigger('click');
        await flushPromises();
        await wrapper.find('.sw-select-option--0').trigger('click');
        await flushPromises();
        const searchBox = wrapper.find('.sw-settings-search-live-search__search_box input');
        await searchBox.setValue(mockResults.oneResult.terms);
        await flushPromises();

        const searchIcon = wrapper.find('.sw-settings-search-live-search__search-icon');
        await searchIcon.trigger('click');
        await flushPromises();

        await wrapper.setData({
            liveSearchResults: mockResults.oneResult.result,
        });
        await flushPromises();

        const firstRow = wrapper.find('.sw-data-grid__row--0');
        expect(firstRow.find('.sw-product-variant-info').exists()).toBeTruthy();
        wrapper.vm.liveSearchService.search.mockReset();
    });

    it('should show multiple results for search', async () => {
        const salesChannelSwitch = wrapper
            .find('.sw-settings-search-live-search__sales-channel-select .sw-select__selection');
        await salesChannelSwitch.trigger('click');
        await flushPromises();
        await wrapper.find('.sw-select-option--0').trigger('click');
        await flushPromises();
        const searchBox = wrapper.find('.sw-simple-search-field input');
        await searchBox.setValue(mockResults.multipleResults.terms);
        await flushPromises();

        await searchBox.trigger('keypress', { key: 'Enter' });
        await flushPromises();

        await wrapper.setData({
            liveSearchResults: mockResults.multipleResults.result,
        });
        await flushPromises();

        const tableBody = wrapper.find('.sw-data-grid__body');
        const firstRow = wrapper.find('.sw-data-grid__row--0');
        const secondRow = wrapper.find('.sw-data-grid__row--1');
        const thirdRow = wrapper.find('.sw-data-grid__row--2');

        expect((tableBody.findAll('.sw-product-variant-info'))).toHaveLength(
            mockResults.multipleResults.result.elements.length,
        );
        expect(firstRow.find('.sw-product-variant-info').exists()).toBeTruthy();
        expect(secondRow.find('.sw-product-variant-info').exists()).toBeTruthy();
        expect(thirdRow.find('.sw-product-variant-info').exists()).toBeTruthy();

        wrapper.vm.liveSearchService.search.mockReset();
    });
});
