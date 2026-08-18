/**
 * @sw-package inventory
 */
import { createWrapper } from './sw-settings-search-live-search-explain.fixtures';

function itemWithClauses(clauses) {
    return {
        extensions: {
            search: {
                _score: 100,
                matched_queries: clauses,
            },
        },
    };
}

describe('src/module/sw-settings-search/component/sw-settings-search-live-search-explain: term coverage', () => {
    let wrapper;

    afterEach(() => {
        wrapper?.unmount();
    });
    it('reports which query words matched and which did not for a multi-word search', async () => {
        wrapper = await createWrapper({ searchTerm: 'marble gaylord' });

        // only "marble" produced a matched clause; "gaylord" hit nothing on this result
        const item = itemWithClauses({
            [JSON.stringify({ field: 'name', term: 'marble', type: 'exact', ranking: 700 })]: 30,
        });

        expect(wrapper.vm.getExplainBreakdown(item).terms).toEqual({ matched: ['marble'], missed: ['gaylord'] });
    });

    it('reports every word as matched when the result hit all query words', async () => {
        wrapper = await createWrapper({ searchTerm: 'marble gaylord' });

        const item = itemWithClauses({
            [JSON.stringify({ field: 'name', term: 'marble', type: 'exact', ranking: 700 })]: 30,
            [JSON.stringify({ field: 'manufacturer.name', term: 'gaylord', type: 'exact', ranking: 500 })]: 20,
        });

        expect(wrapper.vm.getExplainBreakdown(item).terms).toEqual({
            matched: [
                'marble',
                'gaylord',
            ],
            missed: [],
        });
    });

    it('does not count a word as matched because it is a substring of another matched term', async () => {
        wrapper = await createWrapper({ searchTerm: 'iron on' });

        // only "iron" fired a clause; "on" must not count as matched just
        // because it is a substring of "iron"
        const item = itemWithClauses({
            [JSON.stringify({ field: 'name', term: 'iron', type: 'exact', ranking: 700 })]: 30,
        });

        expect(wrapper.vm.getExplainBreakdown(item).terms).toEqual({ matched: ['iron'], missed: ['on'] });
    });

    it('counts every word of a matched phrase term as matched', async () => {
        wrapper = await createWrapper({ searchTerm: 'paper rippers' });

        // the whole-phrase clause carries a multi-word term — each of its words counts
        const item = itemWithClauses({
            [JSON.stringify({ field: 'name', term: 'paper rippers', type: 'phrase', ranking: 700 })]: 17.4,
        });

        expect(wrapper.vm.getExplainBreakdown(item).terms).toEqual({
            matched: [
                'paper',
                'rippers',
            ],
            missed: [],
        });
    });

    it('ignores words the backend tokenizer never queries (too short, pure punctuation)', async () => {
        wrapper = await createWrapper({ searchTerm: 'jeans - blue 5' });

        // "-" (no letter/digit) and "5" (below the minimum search length) are dropped
        // by the backend tokenizer, so they must not be reported as "not matched"
        const item = itemWithClauses({
            [JSON.stringify({ field: 'name', term: 'jeans', type: 'exact', ranking: 700 })]: 30,
        });

        expect(wrapper.vm.getExplainBreakdown(item).terms).toEqual({ matched: ['jeans'], missed: ['blue'] });
    });

    it('does not report term coverage for a single-word search (coverage is trivial)', async () => {
        wrapper = await createWrapper({ searchTerm: 'marble' });

        const item = itemWithClauses({
            [JSON.stringify({ field: 'name', term: 'marble', type: 'exact', ranking: 700 })]: 30,
        });

        expect(wrapper.vm.getExplainBreakdown(item).terms).toBeNull();
    });
});
