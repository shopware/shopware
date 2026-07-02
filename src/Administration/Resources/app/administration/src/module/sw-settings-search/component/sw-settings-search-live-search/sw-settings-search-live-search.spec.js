/**
 * @sw-package inventory
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

const productSortings = [
    {
        key: 'score',
        priority: 10,
        label: 'Top results',
        translated: {
            label: 'Top results',
        },
    },
    {
        key: 'name-asc',
        priority: 2,
        label: 'Name A-Z',
        translated: {
            label: 'Name A-Z',
        },
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
    return mount(
        await wrapTestComponent('sw-settings-search-live-search', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $route: {
                        meta: {
                            $module: {
                                icon: 'regular-icon',
                            },
                        },
                    },
                },
                stubs: {
                    'sw-container': true,
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
                    'sw-settings-search-live-search-keyword': await wrapTestComponent(
                        'sw-settings-search-live-search-keyword',
                    ),
                    'sw-settings-search-example-modal': true,
                    'sw-loader': true,
                    'sw-field-copyable': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                    'sw-checkbox-field': true,
                    'sw-context-menu-item': true,
                    'sw-context-button': true,
                    'sw-data-grid-settings': true,
                    'sw-data-grid-column-boolean': true,
                    'sw-data-grid-inline-edit': true,
                    'router-link': true,
                    'sw-data-grid-skeleton': true,
                    'sw-provide': true,
                },

                provide: {
                    repositoryFactory: {
                        create: (entity) => {
                            if (entity === 'product_sorting') {
                                return {
                                    search: () => {
                                        return Promise.resolve(productSortings);
                                    },
                                };
                            }

                            return {
                                search: () => {
                                    return Promise.resolve(salesChannels);
                                },
                            };
                        },
                    },
                    validationService: {},
                    liveSearchService: {
                        search: jest.fn(({ search: terms }) => {
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
        },
    );
}

describe('src/module/sw-settings-search/component/sw-settings-search-live-search', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
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
        const scoreOrigin = mockResults.oneResult.result.elements[0].extensions.search._score;
        expect(scoreCell.text()).toBe(wrapper.vm.formatScore(scoreOrigin));

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

    it('should build a structured relevance breakdown from Elasticsearch matched_queries', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const item = {
            extensions: {
                search: {
                    _score: 42,
                    matched_queries: {
                        [JSON.stringify({ field: 'name', term: 'iron', type: 'exact', ranking: 1000 })]: 30,
                        [JSON.stringify({ field: 'description', term: 'iron', type: 'prefix', ranking: 500 })]: 12,
                    },
                },
            },
        };

        expect(wrapper.vm.hasExplain(item)).toBe(true);

        const breakdown = wrapper.vm.getExplainBreakdown(item);

        expect(breakdown.total).toBe(42);
        expect(breakdown.sections).toHaveLength(1);

        const rows = breakdown.sections[0].rows;
        // one row per field, ordered strongest-first, weight carried through
        expect(rows.map((row) => row.label)).toEqual(['name', 'description']);
        expect(rows[0].ranking).toBe(1000);
        expect(rows[1].ranking).toBe(500);
        // each field keeps its match clauses as signals; bars share one scale (global max = 30)
        expect(rows[0].signals).toHaveLength(1);
        expect(rows[0].signals[0]).toEqual({ type: 'exact', term: 'iron', score: '30', barWidth: '100%', context: null });
        expect(rows[1].signals[0]).toEqual({ type: 'prefix', term: 'iron', score: '12', barWidth: '40%', context: null });
    });

    it('should show partial (ngram) matches and explain the shared letter fragment', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const item = {
            name: 'Awesome Paper Man Swatter',
            extensions: {
                search: {
                    _score: 745.5,
                    matched_queries: {
                        // "awe" is the start of "Awesome"; "copper" only shares "per" with "Paper"
                        [JSON.stringify({ field: 'name', term: 'awe', type: 'ngram', ranking: 700 })]: 0.6,
                        [JSON.stringify({ field: 'name', term: 'copper', type: 'ngram', ranking: 700 })]: 0.4,
                    },
                },
            },
        };

        const rows = wrapper.vm.getExplainBreakdown(item).sections[0].rows;
        expect(rows).toHaveLength(1);
        // both partial matches stay visible (no longer hidden)
        expect(rows[0].signals.map((signal) => signal.term)).toEqual(['awe', 'copper']);
        // each partial match explains the fragment it shares with the name;
        // `whole` marks that the entire search word appears in the matched word
        expect(rows[0].signals[0].context).toEqual({ fragment: 'awe', word: 'Awesome', whole: true });
        expect(rows[0].signals[1].context).toEqual({ fragment: 'per', word: 'Paper', whole: false });
    });

    it('should label a term by its most specific match type, not the highest-scoring one', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const item = {
            name: 'Awesome Copper Echo Shack',
            extensions: {
                search: {
                    _score: 2623.6,
                    matched_queries: {
                        // "awe" fired BOTH prefix and ngram; ngram scores higher, but prefix is
                        // the more meaningful label — it must win regardless of the lower score
                        [JSON.stringify({ field: 'name', term: 'awe', type: 'prefix', ranking: 700 })]: 0.4,
                        [JSON.stringify({ field: 'name', term: 'awe', type: 'ngram', ranking: 700 })]: 0.6,
                        [JSON.stringify({ field: 'name', term: 'copper', type: 'exact', ranking: 700 })]: 2.8,
                    },
                },
            },
        };

        const signals = wrapper.vm.getExplainBreakdown(item).sections[0].rows[0].signals;
        expect(signals.map((signal) => signal.term)).toEqual(['copper', 'awe']);
        expect(signals.map((signal) => signal.type)).toEqual(['exact', 'prefix']);
        // exact is self-explanatory; prefix points at the word it starts
        expect(signals[0].context).toBeNull();
        expect(signals[1].context).toEqual({ fragment: 'awe', word: 'Awesome', whole: true });
    });

    it('should not compute a fragment explanation for non-partial matches', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const item = {
            name: 'Awesome Copper Echo Shack',
            extensions: {
                search: {
                    _score: 42,
                    matched_queries: {
                        [JSON.stringify({ field: 'name', term: 'copper', type: 'exact', ranking: 700 })]: 2.8,
                    },
                },
            },
        };

        const signal = wrapper.vm.getExplainBreakdown(item).sections[0].rows[0].signals[0];
        expect(signal.type).toBe('exact');
        expect(signal.context).toBeNull();
    });

    it('should present a whole-phrase (multi-word) match as a "phrase" match without a single-word fragment', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const item = {
            name: 'Mediocre Paper Rippers',
            extensions: {
                search: {
                    _score: 8048.4,
                    matched_queries: {
                        [JSON.stringify({ field: 'name', term: 'rippers', type: 'exact', ranking: 700 })]: 5.9,
                        // the whole-search-phrase clause (ProductSearchQueryBuilder searches the
                        // full term too) arrives as a multi-word term with a phrase-prefix type
                        [JSON.stringify({ field: 'name', term: 'mediocre paper rippers', type: 'prefix', ranking: 700 })]: 3.1,
                    },
                },
            },
        };

        const signals = wrapper.vm.getExplainBreakdown(item).sections[0].rows[0].signals;
        const phraseSignal = signals.find((signal) => signal.term === 'mediocre paper rippers');

        // detected from the backend matched_queries (term === the full search phrase), not by
        // comparing strings on the front end
        expect(phraseSignal.type).toBe('phrase');
        // no misleading single-word fragment ("rippers" in "Rippers") for a phrase match
        expect(phraseSignal.context).toBeNull();
    });

    it('should keep the boosted match_phrase clause over the weaker match_phrase_prefix for the same phrase', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const item = {
            name: 'Mediocre Paper Rippers',
            extensions: {
                search: {
                    _score: 8048.4,
                    matched_queries: {
                        // both fire for the multi-word term: the boosted match_phrase (strong)
                        // and the legacy match_phrase_prefix (weak). The strong one must win the
                        // per-term dedup so the panel shows its real (high) score.
                        [JSON.stringify({ field: 'name', term: 'paper rippers', type: 'phrase', ranking: 700 })]: 17.4,
                        [JSON.stringify({ field: 'name', term: 'paper rippers', type: 'prefix', ranking: 700 })]: 2.6,
                    },
                },
            },
        };

        const signals = wrapper.vm.getExplainBreakdown(item).sections[0].rows[0].signals;
        const phraseSignals = signals.filter((signal) => signal.term === 'paper rippers');

        expect(phraseSignals).toHaveLength(1);
        expect(phraseSignals[0].type).toBe('phrase');
        expect(phraseSignals[0].score).toBe('17.4');
    });

    it('should keep only the strongest match type per term', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        // translated fields are indexed under `name.<languageId>` — the row must read "name"
        const nameField = 'name.2fbb5fe2e29a4d70aa5854ce7ce3e7a0';

        const item = {
            extensions: {
                search: {
                    _score: 42,
                    matched_queries: {
                        // "copper" fired exact AND partial — only exact (the strongest) should survive
                        [JSON.stringify({ field: nameField, term: 'copper', type: 'exact', ranking: 1000 })]: 30,
                        [JSON.stringify({ field: nameField, term: 'copper', type: 'ngram', ranking: 1000 })]: 8,
                        // "awe" only ever matched as a prefix — that must still surface
                        [JSON.stringify({ field: nameField, term: 'awe', type: 'prefix', ranking: 1000 })]: 12,
                    },
                },
            },
        };

        const rows = wrapper.vm.getExplainBreakdown(item).sections[0].rows;
        expect(rows).toHaveLength(1);
        expect(rows[0].label).toBe('name');
        expect(rows[0].ranking).toBe(1000);
        // one signal per term, ordered strongest-first — "copper" keeps exact, drops its ngram duplicate
        expect(rows[0].signals).toHaveLength(2);
        expect(rows[0].signals.map((signal) => signal.term)).toEqual(['copper', 'awe']);
        expect(rows[0].signals.map((signal) => signal.type)).toEqual(['exact', 'prefix']);
        expect(rows[0].signals.map((signal) => signal.score)).toEqual(['30', '12']);
        expect(rows[0].signals[0].barWidth).toBe('100%');
        expect(rows[0].signals[1].barWidth).toBe('40%');

        expect(wrapper.vm.humanizeField(`${nameField}.search`)).toBe('name');
        expect(wrapper.vm.humanizeField('customFields.2fbb5fe2e29a4d70aa5854ce7ce3e7a0.material')).toBe('customFields.material');
        expect(wrapper.vm.explainTypeLabel('fuzzy')).toBe('sw-settings-search.liveSearchTab.matchType.fuzzy');
        expect(wrapper.vm.explainTypeLabel(null)).toBe('');
    });

    it('should flag uniform result scores so the order can be explained as a tie', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

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

    it('should skip the explain breakdown when there are no matched_queries (e.g. MySQL search)', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const item = { extensions: { search: { _score: 42 } } };

        expect(wrapper.vm.hasExplain(item)).toBe(false);
        expect(wrapper.vm.getExplainBreakdown(item)).toBeNull();
    });

    it('should not include boost or cross-entity clauses in the core relevance breakdown', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const item = {
            extensions: {
                search: {
                    _score: 42,
                    matched_queries: {
                        [JSON.stringify({ field: 'name', term: 'iron' })]: 30,
                        [JSON.stringify({ boost: 5, name: 'My boosting rule' })]: 99,
                        [JSON.stringify({ crossEntity: 'category', term: 'iron' })]: 7,
                    },
                },
            },
        };

        const breakdown = wrapper.vm.getExplainBreakdown(item);
        const labels = breakdown.sections.flatMap((section) => section.rows.map((row) => row.label));

        // core only explains field relevance
        expect(labels).toContain('name');
        expect(labels).not.toContain('My boosting rule');
        expect(labels).not.toContain('category');
    });

    it('should render the score cell with rank, bar and unrounded value', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({ liveSearchResults: mockResults.multipleResults.result });
        await flushPromises();

        const firstRow = wrapper.find('.sw-data-grid__row--0');
        expect(firstRow.find('.sw-settings-search-live-search__score-rank').text()).toBe('#1');
        expect(firstRow.find('.sw-settings-search-live-search__score-bar-fill').exists()).toBe(true);

        const expected = wrapper.vm.formatScore(mockResults.multipleResults.result.elements[0].extensions.search._score);
        expect(firstRow.find('.sw-settings-search-live-search__score-value').text()).toBe(expected);
    });

    it('should close the explain panel when a new search is run', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({ liveSearchTerm: 'iron', selectedExplainId: 'stale-id' });
        wrapper.vm.searchOnStorefront();

        expect(wrapper.vm.selectedExplainId).toBeNull();
    });

    it('should translate known field labels and fall back to the raw field otherwise', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        // known fields resolve to the same snippet the search-config table uses
        expect(wrapper.vm.fieldLabel('name')).toBe('sw-settings-search.generalTab.configFields.name');
        expect(wrapper.vm.fieldLabel('customSearchKeywords'))
            .toBe('sw-settings-search.generalTab.configFields.customSearchKeywords');
        expect(wrapper.vm.fieldLabel('manufacturer.name'))
            .toBe('sw-settings-search.generalTab.configFields.manufacturerName');
        // unknown fields (custom fields, boost rule names) fall back to the raw value
        expect(wrapper.vm.fieldLabel('customFields.material')).toBe('customFields.material');
    });

    it('should offset the rank by resultOffset so ranks continue across pages', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

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
