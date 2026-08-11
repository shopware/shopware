/**
 * @sw-package inventory
 */
import { createWrapper, mockResults } from './sw-settings-search-live-search.fixtures';

describe('src/module/sw-settings-search/component/sw-settings-search-live-search: search and results', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    afterEach(() => {
        wrapper?.unmount();
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

        const salesChannelSwitch = wrapper.find(
            '.sw-settings-search-live-search__sales-channel-select .sw-select__selection',
        );
        await salesChannelSwitch.trigger('click');
        await flushPromises();
        await wrapper.find('.sw-select-option--0').trigger('click');
        expect(searchBox.attributes().disabled).toBeFalsy();
    });

    it('should show no results message if search keywords is nothing', async () => {
        const salesChannelSwitch = wrapper.find(
            '.sw-settings-search-live-search__sales-channel-select .sw-select__selection',
        );
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
        const resultHeadline = wrapper.find('.sw-settings-search-live-search__no-result .mt-empty-state__headline');
        expect(resultHeadline.text()).toBe('sw-settings-search.liveSearchTab.textNoResult');
        wrapper.vm.liveSearchService.search.mockReset();
    });

    it('should show one result for search', async () => {
        const salesChannelSwitch = wrapper.find(
            '.sw-settings-search-live-search__sales-channel-select .sw-select__selection',
        );
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

        const scoreCell = firstRow.find('.sw-settings-search-live-search__score-value');
        // independent expectation (not derived from formatScore itself):
        // 28799.999999 keeps one decimal instead of being rounded to an integer
        expect(scoreCell.text()).toBe('28800.0');

        wrapper.vm.liveSearchService.search.mockReset();
    });

    it('should show multiple results for search', async () => {
        const salesChannelSwitch = wrapper.find(
            '.sw-settings-search-live-search__sales-channel-select .sw-select__selection',
        );
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

        expect(tableBody.findAll('.sw-product-variant-info')).toHaveLength(
            mockResults.multipleResults.result.elements.length,
        );
        expect(firstRow.find('.sw-product-variant-info').exists()).toBeTruthy();
        expect(secondRow.find('.sw-product-variant-info').exists()).toBeTruthy();
        expect(thirdRow.find('.sw-product-variant-info').exists()).toBeTruthy();

        wrapper.vm.liveSearchService.search.mockReset();
    });

    it('should call search service with correct sorting parameter', async () => {
        jest.useFakeTimers();
        wrapper.vm.liveSearchService.search.mockClear();

        const searchSpy = jest.spyOn(wrapper.vm.liveSearchService, 'search');

        // Select sales channel
        const salesChannelSwitch = wrapper.find(
            '.sw-settings-search-live-search__sales-channel-select .sw-select__selection',
        );
        await salesChannelSwitch.trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-option--0').trigger('click');
        await flushPromises();

        // The component fetches sortings on created, and sets default to 'score'.
        // Let's check if the default is passed first.
        const searchBox = wrapper.find('.sw-simple-search-field input');
        await searchBox.setValue(mockResults.multipleResults.terms);
        await flushPromises();
        jest.runAllTimers();
        await flushPromises();

        expect(searchSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                search: mockResults.multipleResults.terms,
                order: 'score',
            }),
            {},
            {},
            expect.any(Object),
        );

        searchSpy.mockClear();

        const sortingSelect = wrapper.find('.sw-settings-search-live-search__sorting-select');
        await sortingSelect.find('.mt-select__selection').trigger('click');
        await flushPromises();

        // The component sorts by priority, so 'score' (10) is first, 'name-asc' (2) is second.
        await sortingSelect.find('.mt-select-option--1').trigger('click');
        await flushPromises();
        jest.runAllTimers();
        await flushPromises();

        // The search should be triggered on value change of sorting select
        expect(searchSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                order: 'name-asc',
                search: mockResults.multipleResults.terms,
            }),
            {},
            {},
            expect.any(Object),
        );
        jest.useRealTimers();
    });

    it('should render the score cell with rank, bar and unrounded value', async () => {
        await wrapper.setData({ liveSearchResults: mockResults.multipleResults.result });
        await flushPromises();

        const firstRow = wrapper.find('.sw-data-grid__row--0');
        expect(firstRow.find('.sw-settings-search-live-search__score-rank').text()).toBe('#1');
        expect(firstRow.find('.sw-settings-search-live-search__score-bar-fill').exists()).toBe(true);

        // independent expectation: a whole number renders without a decimal
        expect(firstRow.find('.sw-settings-search-live-search__score-value').text()).toBe('40320');
    });

    it('ignores a sorting change before any search term was entered', async () => {
        // the searchTerms prop defaults to null — changing the sorting select
        // triggers searchOnStorefront and must not crash on null.length
        await wrapper.setData({ liveSearchTerm: null });

        expect(() => wrapper.vm.searchOnStorefront()).not.toThrow();
        expect(wrapper.vm.liveSearchService.search).not.toHaveBeenCalled();
    });

    it('should flag uniform result scores so the order can be explained as a tie', async () => {
        const tie = [
            { name: 'Tied A', extensions: { search: { _score: 100 } } },
            { name: 'Tied B', extensions: { search: { _score: 100 } } },
        ];
        await wrapper.setData({ liveSearchResults: { elements: tie } });
        await flushPromises();
        expect(wrapper.vm.scoresAreUniform).toBe(true);

        await wrapper.setData({ liveSearchResults: mockResults.multipleResults.result });
        await flushPromises();
        expect(wrapper.vm.scoresAreUniform).toBe(false);
    });

    it('should offset the rank by resultOffset so ranks continue across pages', async () => {
        await wrapper.setData({ liveSearchResults: mockResults.multipleResults.result });
        await flushPromises();

        const items = wrapper.vm.resultItems;
        // core loads results client-side and paginates in the grid → no page offset;
        // the AdvancedSearch override sets resultOffset to (page - 1) * limit
        expect(wrapper.vm.resultOffset).toBe(0);
        expect(wrapper.vm.getRank(items[0])).toBe(1);
        expect(wrapper.vm.getRank(items[2])).toBe(3);
    });
});
