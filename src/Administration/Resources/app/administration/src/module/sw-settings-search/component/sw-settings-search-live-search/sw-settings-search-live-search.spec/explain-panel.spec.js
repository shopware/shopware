/**
 * @sw-package inventory
 */
import { createWrapper } from './sw-settings-search-live-search.fixtures';

describe('src/module/sw-settings-search/component/sw-settings-search-live-search: explain panel state', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    afterEach(() => {
        wrapper?.unmount();
    });

    it('should mark a row explainable only when a field clause matched', async () => {
        // a field clause → explainable
        const item = {
            extensions: {
                search: {
                    _score: 42,
                    matched_queries: {
                        [JSON.stringify({ field: 'name', term: 'iron', type: 'exact', ranking: 1000 })]: 30,
                    },
                },
            },
        };
        expect(wrapper.vm.hasExplain(item)).toBe(true);

        // no matched_queries at all (e.g. MySQL search)
        expect(wrapper.vm.hasExplain({ extensions: { search: { _score: 42 } } })).toBe(false);

        // present but empty map (e.g. a PHP empty array serialized as [])
        expect(wrapper.vm.hasExplain({ extensions: { search: { _score: 1, matched_queries: {} } } })).toBe(false);

        // only boost / cross-entity clauses — filtered from the core breakdown,
        // so the score cell must not render as a button that opens nothing
        const boostOnly = {
            extensions: {
                search: {
                    _score: 1,
                    matched_queries: {
                        [JSON.stringify({ boost: 5, name: 'My boosting rule' })]: 99,
                        [JSON.stringify({ crossEntity: 'category', term: 'iron' })]: 7,
                    },
                },
            },
        };
        expect(wrapper.vm.hasExplain(boostOnly)).toBe(false);
    });

    it('opens the explain panel for the clicked row', async () => {
        const item = {
            id: 'product-1',
            name: 'Durable Iron',
            extensions: {
                search: {
                    _score: 10,
                    matched_queries: {
                        [JSON.stringify({ field: 'name', term: 'iron', type: 'exact', ranking: 700 })]: 3,
                    },
                },
            },
        };
        await wrapper.setData({ liveSearchResults: { elements: [item] } });
        await flushPromises();

        const scoreButton = wrapper.find('.sw-settings-search-live-search__score');
        expect(scoreButton.element.tagName).toBe('BUTTON');
        // the toggle announces its state and target to assistive technology
        expect(scoreButton.attributes('aria-expanded')).toBe('false');
        expect(scoreButton.attributes('aria-controls')).toBe('sw-settings-search-live-search-explain');

        await scoreButton.trigger('click');

        expect(scoreButton.attributes('aria-expanded')).toBe('true');
        expect(wrapper.vm.selectedExplainId).toBe('product-1');
        expect(wrapper.vm.selectedExplainItem).toStrictEqual(item);
        expect(wrapper.find('sw-settings-search-live-search-explain-stub').exists()).toBe(true);

        // clicking again closes the panel
        await scoreButton.trigger('click');
        expect(wrapper.vm.selectedExplainId).toBeNull();
        expect(wrapper.find('sw-settings-search-live-search-explain-stub').exists()).toBe(false);
    });

    it('should close the explain panel when a new search is run', async () => {
        await wrapper.setData({ liveSearchTerm: 'iron', selectedExplainId: 'stale-id' });
        wrapper.vm.searchOnStorefront();

        expect(wrapper.vm.selectedExplainId).toBeNull();
    });

    it('should close the explain panel when the search input is cleared', async () => {
        // the empty-term early return must not skip the panel reset — the
        // results (and the panel) no longer match what is in the box
        await wrapper.setData({ liveSearchTerm: '', selectedExplainId: 'stale-id' });
        wrapper.vm.searchOnStorefront();

        expect(wrapper.vm.selectedExplainId).toBeNull();
    });

    it('snapshots the executed term so an open panel ignores keystrokes in the search box', async () => {
        await wrapper.setData({ liveSearchTerm: 'iron' });
        wrapper.vm.searchOnStorefront();
        // the snapshot is taken only once the results arrive, not at call time
        await flushPromises();
        expect(wrapper.vm.executedSearchTerm).toBe('iron');

        // typing changes the live input but no search has run yet — the explain
        // panel must keep reading the term the results were produced by
        await wrapper.setData({ liveSearchTerm: 'iron man' });
        expect(wrapper.vm.currentSearchTerm).toBe('iron');
    });

    it('keeps the previously executed term when a search fails', async () => {
        // a prior successful search is reflected in the snapshot
        await wrapper.setData({ liveSearchTerm: 'iron', executedSearchTerm: 'iron' });

        // the next search rejects — its term must not become the executed term,
        // or the panel would compute coverage against a search that never landed
        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.liveSearchService.search.mockRejectedValueOnce(new Error('boom'));
        await wrapper.setData({ liveSearchTerm: 'steel' });
        wrapper.vm.searchOnStorefront();
        await flushPromises();

        expect(wrapper.vm.executedSearchTerm).toBe('iron');
        expect(wrapper.vm.currentSearchTerm).toBe('iron');
    });
});
