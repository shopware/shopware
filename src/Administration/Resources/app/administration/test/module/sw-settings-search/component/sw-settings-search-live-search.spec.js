import { createLocalVue, shallowMount } from '@vue/test-utils';
import flushPromises from 'flush-promises';
import 'src/module/sw-settings-search/component/sw-settings-search-live-search';
import 'src/module/sw-settings-search/component/sw-settings-search-live-search-keyword';
import 'src/app/component/base/sw-simple-search-field';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/utils/sw-popover';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/base/sw-product-variant-info';
import 'src/app/component/base/sw-highlight-text';

const salesChannels = [
    {
        name: 'Storefront',
        id: '7e0e4a256138402c82a20fcbb4fbb858'
    },
    {

        name: 'Headless',
        id: '98432def39fc4624b33213a56b8c944d'
    }
];

const mockResults = {
    nothing: {
        terms: 'nothing',
        result: {
            elements: []
        }
    },
    oneResult: {
        terms: 'iron',
        result: {
            elements: [
                {
                    name: 'Durable Iron OpenDoor',
                    extensions: {
                        search: {
                            _score: 28799.999999
                        }
                    }
                }
            ]
        }
    },
    multipleResults: {
        terms: 'awesome',
        result: {
            elements: [
                {
                    name: 'Awesome Copper Belly-flop Buffet',
                    extensions: {
                        search: {
                            _score: 40320
                        }
                    }
                },
                {
                    name: 'Awesome Wooden Crystal Qlear',
                    extensions: {
                        search: {
                            _score: 34560
                        }
                    }
                },
                {
                    name: 'Awesome Silk Ghost Voices',
                    extensions: {
                        search: {
                            _score: 34559.9999
                        }
                    }
                }
            ]
        }
    }
};

function createWrapper() {
    const localVue = createLocalVue();
    localVue.directive('popover', {});
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-settings-search-live-search'), {
        localVue,

        stubs: {
            'sw-card': true,
            'sw-container': true,
            'sw-button': true,
            'sw-icon': true,
            'sw-field-error': true,
            'sw-simple-search-field': Shopware.Component.build('sw-simple-search-field'),
            'sw-field': Shopware.Component.build('sw-field'),
            'sw-text-field': Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-select-base': Shopware.Component.build('sw-select-base'),
            'sw-single-select': Shopware.Component.build('sw-single-select'),
            'sw-highlight-text': Shopware.Component.build('sw-highlight-text'),
            'sw-select-result': Shopware.Component.build('sw-select-result'),
            'sw-select-result-list': Shopware.Component.build('sw-select-result-list'),
            'sw-popover': Shopware.Component.build('sw-popover'),
            'sw-data-grid': Shopware.Component.build('sw-data-grid'),
            'sw-product-variant-info': Shopware.Component.build('sw-product-variant-info'),
            'sw-settings-search-live-search-keyword': Shopware.Component.build('sw-settings-search-live-search-keyword')
        },

        propsData: {
            currentSalesChannelId: null,
            searchTerms: '',
            searchResults: {}
        },

        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => {
                        return Promise.resolve(salesChannels);
                    }
                })
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
                })
            }
        }
    });
}

describe('src/module/sw-settings-search/component/sw-settings-search-live-search', () => {
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

    it('should render the sales channel select', async () => {
        expect(wrapper.find('.sw-settings-search-live-search__sales-channel-select').exists()).toBeTruthy();
    });

    it('should show the search box disabled on no sales channel selected', async () => {
        const searchBox = wrapper.find('.sw-simple-search-field input');
        expect(searchBox.attributes().disabled).toBeTruthy();
    });

    it('should enable the search box after set the sales channel id', async () => {
        const searchBox = wrapper.find('.sw-simple-search-field input');
        expect(searchBox.attributes().disabled).toBeTruthy();

        const salesChannelSwitch = wrapper
            .find('.sw-settings-search-live-search__sales-channel-select .sw-select__selection');
        await salesChannelSwitch.trigger('click');
        await wrapper.find('.sw-select-option--0').trigger('click');
        expect(searchBox.attributes().disabled).toBeFalsy();
    });

    it('should show no results message if search keywords is nothing', async () => {
        const salesChannelSwitch = wrapper
            .find('.sw-settings-search-live-search__sales-channel-select .sw-select__selection');
        await salesChannelSwitch.trigger('click');
        await wrapper.find('.sw-select-option--0').trigger('click');
        const searchBox = wrapper.find('.sw-simple-search-field input');
        await searchBox.setValue(mockResults.nothing.terms);

        searchBox.trigger('keypress', { key: 'Enter' });
        await flushPromises();

        await wrapper.setData({
            liveSearchResults: mockResults.nothing.result
        });
        const resultText = wrapper.find('.sw-settings-search-live-search__no-result');
        expect(resultText.text()).toEqual('sw-settings-search.liveSearchTab.textNoResult');
        wrapper.vm.liveSearchService.search.mockReset();
    });

    it('should show one result for search', async () => {
        const salesChannelSwitch = wrapper
            .find('.sw-settings-search-live-search__sales-channel-select .sw-select__selection');
        await salesChannelSwitch.trigger('click');
        await wrapper.find('.sw-select-option--0').trigger('click');
        const searchBox = wrapper.find('.sw-simple-search-field input');
        await searchBox.setValue(mockResults.oneResult.terms);

        searchBox.trigger('keypress', { key: 'Enter' });
        await flushPromises();

        await wrapper.setData({
            liveSearchResults: mockResults.oneResult.result
        });

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
        await wrapper.find('.sw-select-option--0').trigger('click');
        const searchBox = wrapper.find('.sw-settings-search-live-search__search_box input');
        await searchBox.setValue(mockResults.oneResult.terms);

        const searchIcon = wrapper.find('.sw-settings-search-live-search__search-icon');
        searchIcon.trigger('click');
        await flushPromises();

        await wrapper.setData({
            liveSearchResults: mockResults.oneResult.result
        });

        const firstRow = wrapper.find('.sw-data-grid__row--0');
        expect(firstRow.find('.sw-product-variant-info').exists()).toBeTruthy();
        wrapper.vm.liveSearchService.search.mockReset();
    });

    it('should show multiple results for search', async () => {
        const salesChannelSwitch = wrapper
            .find('.sw-settings-search-live-search__sales-channel-select .sw-select__selection');
        await salesChannelSwitch.trigger('click');
        await wrapper.find('.sw-select-option--0').trigger('click');
        const searchBox = wrapper.find('.sw-simple-search-field input');
        await searchBox.setValue(mockResults.multipleResults.terms);

        searchBox.trigger('keypress', { key: 'Enter' });
        await flushPromises();

        await wrapper.setData({
            liveSearchResults: mockResults.multipleResults.result
        });

        const tableBody = wrapper.find('.sw-data-grid__body');
        const firstRow = wrapper.find('.sw-data-grid__row--0');
        const secondRow = wrapper.find('.sw-data-grid__row--1');
        const thirdRow = wrapper.find('.sw-data-grid__row--2');

        expect((tableBody.findAll('.sw-product-variant-info').length)).toBe(
            mockResults.multipleResults.result.elements.length
        );
        expect(firstRow.find('.sw-product-variant-info').exists()).toBeTruthy();
        expect(secondRow.find('.sw-product-variant-info').exists()).toBeTruthy();
        expect(thirdRow.find('.sw-product-variant-info').exists()).toBeTruthy();

        wrapper.vm.liveSearchService.search.mockReset();
    });
});
