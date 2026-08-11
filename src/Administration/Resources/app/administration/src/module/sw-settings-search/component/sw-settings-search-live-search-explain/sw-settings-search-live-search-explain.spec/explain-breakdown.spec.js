/**
 * @sw-package inventory
 */
import { createWrapper } from './sw-settings-search-live-search-explain.fixtures';

describe('src/module/sw-settings-search/component/sw-settings-search-live-search-explain: breakdown', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(() => {
        wrapper?.unmount();
    });

    it('should build a structured relevance breakdown from Elasticsearch matched_queries', async () => {
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

        const breakdown = wrapper.vm.getExplainBreakdown(item);

        expect(breakdown.total).toBe(42);
        expect(breakdown.sections).toHaveLength(1);

        const rows = breakdown.sections[0].rows;
        // one row per field, ordered strongest-first, weight carried through
        expect(rows.map((row) => row.label)).toEqual([
            'name',
            'description',
        ]);
        expect(rows[0].ranking).toBe(1000);
        expect(rows[1].ranking).toBe(500);
        // each field keeps its match clauses as signals; per-clause (text field) scores are
        // scaled by the field weight so they compare with weighted nested/leaf scores
        // (30 * 1000 = 30000, 12 * 500 = 6000); bars share one scale (global max = 30000)
        expect(rows[0].signals).toHaveLength(1);
        expect(rows[0].signals[0]).toEqual({ type: 'exact', term: 'iron', score: '30000', barWidth: '100%', context: null });
        expect(rows[1].signals[0]).toEqual({ type: 'prefix', term: 'iron', score: '6000', barWidth: '20%', context: null });
    });

    it('scales per-clause (text) field scores by their weight so they compare with already-weighted nested scores', async () => {
        const item = {
            extensions: {
                search: {
                    _score: 4100,
                    matched_queries: {
                        // text field: named per clause -> raw relevance, must be scaled by the weight (700)
                        [JSON.stringify({ field: 'name', term: 'marble', type: 'exact', ranking: 700 })]: 2.8,
                        // nested field: named at field level (no match type — the clauses
                        // inside the nested query decide how it matched) -> its score
                        // already carries the weight (500)
                        [JSON.stringify({
                            field: 'manufacturer.name',
                            term: 'gaylord',
                            ranking: 500,
                            weighted: true,
                        })]: 2121.7,
                    },
                },
            },
        };

        const rows = wrapper.vm.getExplainBreakdown(item).sections[0].rows;
        const byField = Object.fromEntries(
            rows.map((row) => [
                row.label,
                row,
            ]),
        );

        // product name: 2.8 * 700 = 1960 (previously shown as a dwarfed 2.8; float math -> one decimal)
        expect(byField.name.signals[0].score).toBe('1960.0');
        // manufacturer: already weighted -> kept as-is
        expect(byField['manufacturer.name'].signals[0].score).toBe('2121.7');
        // both bars now on a comparable scale (not 0.1% vs 100%)
        expect(parseFloat(byField.name.signals[0].barWidth)).toBeGreaterThan(50);
    });

    it('should show partial (ngram) matches and explain the shared letter fragment', async () => {
        const item = {
            name: 'Awesome Paper Man Swatter',
            extensions: {
                search: {
                    _score: 745.5,
                    matched_queries: {
                        // "awes" appears whole in "Awesome"; "batter" only shares "atter" with "Swatter"
                        [JSON.stringify({ field: 'name', term: 'awes', type: 'ngram', ranking: 700 })]: 0.6,
                        [JSON.stringify({ field: 'name', term: 'batter', type: 'ngram', ranking: 700 })]: 0.4,
                    },
                },
            },
        };

        const rows = wrapper.vm.getExplainBreakdown(item).sections[0].rows;
        expect(rows).toHaveLength(1);
        // both partial matches stay visible (no longer hidden)
        expect(rows[0].signals.map((signal) => signal.term)).toEqual([
            'awes',
            'batter',
        ]);
        // each partial match explains the fragment it shares with the name;
        // `whole` marks that the entire search word appears in the matched word
        expect(rows[0].signals[0].context).toEqual({ fragment: 'awes', word: 'Awesome', whole: true });
        expect(rows[0].signals[1].context).toEqual({ fragment: 'atter', word: 'Swatter', whole: false });
    });

    it('does not name a fragment shorter than the default ngram floor', async () => {
        const item = {
            name: 'Awesome Paper Man Swatter',
            extensions: {
                search: {
                    _score: 10,
                    matched_queries: {
                        // "copper" shares only the 3-char "per" with "Paper" — below the
                        // min_gram default of 4, so the analyzer could not have matched it
                        // and the panel must not claim it did
                        [JSON.stringify({ field: 'name', term: 'copper', type: 'ngram', ranking: 700 })]: 0.4,
                    },
                },
            },
        };

        const signal = wrapper.vm.getExplainBreakdown(item).sections[0].rows[0].signals[0];
        expect(signal.context).toBeNull();
    });

    it('explains a partial match across ascii-folded characters (ü, ß) like the analyzer does', async () => {
        const item = {
            name: 'Müller Bohrer',
            extensions: {
                search: {
                    _score: 10,
                    matched_queries: {
                        [JSON.stringify({ field: 'name', term: 'muller', type: 'ngram', ranking: 700 })]: 0.6,
                    },
                },
            },
        };

        // the fragment comparison folds like the analyzer: "muller" IS "Müller",
        // not a four-letter "ller" overlap
        const signal = wrapper.vm.getExplainBreakdown(item).sections[0].rows[0].signals[0];
        expect(signal.context).toEqual({ fragment: 'muller', word: 'Müller', whole: true });

        const eszett = {
            name: 'Straße Lampe',
            extensions: {
                search: {
                    _score: 10,
                    matched_queries: {
                        [JSON.stringify({ field: 'name', term: 'strasse', type: 'ngram', ranking: 700 })]: 0.6,
                    },
                },
            },
        };

        const eszettSignal = wrapper.vm.getExplainBreakdown(eszett).sections[0].rows[0].signals[0];
        expect(eszettSignal.context).toEqual({ fragment: 'strasse', word: 'Straße', whole: true });
    });

    it('should label a term by its most specific match type, not the highest-scoring one', async () => {
        const item = {
            name: 'Awesome Copper Echo Shack',
            extensions: {
                search: {
                    _score: 2623.6,
                    matched_queries: {
                        // "awes" fired BOTH prefix and ngram; ngram scores higher, but prefix is
                        // the more meaningful label — it must win regardless of the lower score
                        [JSON.stringify({ field: 'name', term: 'awes', type: 'prefix', ranking: 700 })]: 0.4,
                        [JSON.stringify({ field: 'name', term: 'awes', type: 'ngram', ranking: 700 })]: 0.6,
                        [JSON.stringify({ field: 'name', term: 'copper', type: 'exact', ranking: 700 })]: 2.8,
                    },
                },
            },
        };

        const signals = wrapper.vm.getExplainBreakdown(item).sections[0].rows[0].signals;
        expect(signals.map((signal) => signal.term)).toEqual([
            'copper',
            'awes',
        ]);
        expect(signals.map((signal) => signal.type)).toEqual([
            'exact',
            'prefix',
        ]);
        // exact is self-explanatory; prefix points at the word it starts
        expect(signals[0].context).toBeNull();
        expect(signals[1].context).toEqual({ fragment: 'awes', word: 'Awesome', whole: true });
    });

    it('should not compute a fragment explanation for non-partial matches', async () => {
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
        const item = {
            name: 'Mediocre Paper Rippers',
            extensions: {
                search: {
                    _score: 8048.4,
                    matched_queries: {
                        [JSON.stringify({ field: 'name', term: 'rippers', type: 'exact', ranking: 700 })]: 5.9,
                        // the whole-search-phrase clause arrives tagged 'phrase' by the backend,
                        // with the field ranking already folded into its score
                        [JSON.stringify({
                            field: 'name',
                            term: 'mediocre paper rippers',
                            type: 'phrase',
                            ranking: 700,
                            weighted: true,
                        })]: 2170,
                    },
                },
            },
        };

        const signals = wrapper.vm.getExplainBreakdown(item).sections[0].rows[0].signals;
        const phraseSignal = signals.find((signal) => signal.term === 'mediocre paper rippers');

        // the type is taken as the backend reports it — no front-end re-labelling
        expect(phraseSignal.type).toBe('phrase');
        // no misleading single-word fragment ("rippers" in "Rippers") for a phrase match
        expect(phraseSignal.context).toBeNull();
    });

    it('should keep the weighted phrase clause score as the backend reports it', async () => {
        const item = {
            name: 'Mediocre Paper Rippers',
            extensions: {
                search: {
                    _score: 8048.4,
                    matched_queries: {
                        // the phrase clause's boost folds the field ranking, so the backend
                        // flags it `weighted` and its score arrives already weight-scaled —
                        // it must be kept as-is, NOT scaled by the ranking again
                        [JSON.stringify({
                            field: 'name',
                            term: 'paper rippers',
                            type: 'phrase',
                            ranking: 700,
                            weighted: true,
                        })]: 12180,
                    },
                },
            },
        };

        const signals = wrapper.vm.getExplainBreakdown(item).sections[0].rows[0].signals;
        const phraseSignals = signals.filter((signal) => signal.term === 'paper rippers');

        expect(phraseSignals).toHaveLength(1);
        expect(phraseSignals[0].type).toBe('phrase');
        // already weighted by the backend -> kept as-is, not multiplied by 700 again
        expect(phraseSignals[0].score).toBe('12180');
        // no single-word fragment hint for a phrase match
        expect(phraseSignals[0].context).toBeNull();
    });

    it('should keep only the strongest match type per term', async () => {
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
        expect(rows[0].signals.map((signal) => signal.term)).toEqual([
            'copper',
            'awe',
        ]);
        expect(rows[0].signals.map((signal) => signal.type)).toEqual([
            'exact',
            'prefix',
        ]);
        // per-clause scores scaled by the field weight (30 * 1000, 12 * 1000)
        expect(rows[0].signals.map((signal) => signal.score)).toEqual([
            '30000',
            '12000',
        ]);
        expect(rows[0].signals[0].barWidth).toBe('100%');
        expect(rows[0].signals[1].barWidth).toBe('40%');

        expect(wrapper.vm.humanizeField(`${nameField}.search`)).toBe('name');
        expect(wrapper.vm.humanizeField('customFields.2fbb5fe2e29a4d70aa5854ce7ce3e7a0.material')).toBe(
            'customFields.material',
        );
    });

    it('should fall back to the raw match type when no snippet exists for it', async () => {
        // $t returns the key untranslated in the test env, so the missing-snippet
        // fallback fires — the same path an unknown plugin-supplied type takes in
        // production: the raw type, never a leaked snippet key
        expect(wrapper.vm.explainTypeLabel('semantic')).toBe('semantic');
        expect(wrapper.vm.explainTypeTooltip('semantic')).toBe('semantic');
        expect(wrapper.vm.explainTypeLabel('fuzzy')).toBe('fuzzy');
        expect(wrapper.vm.explainTypeLabel(null)).toBe('');
        expect(wrapper.vm.explainTypeTooltip(null)).toBe('');
    });

    it('should return no breakdown when matched_queries is missing, empty or yields no field rows', async () => {
        // no matched_queries at all (e.g. MySQL search)
        expect(wrapper.vm.getExplainBreakdown({ extensions: { search: { _score: 42 } } })).toBeNull();

        // present but empty map (e.g. a PHP empty array serialized as [])
        expect(wrapper.vm.getExplainBreakdown({ extensions: { search: { _score: 1, matched_queries: {} } } })).toBeNull();

        // only boost clauses — filtered from the core breakdown
        const boostOnly = {
            extensions: {
                search: {
                    _score: 1,
                    matched_queries: {
                        [JSON.stringify({ boost: 5, name: 'My boosting rule' })]: 99,
                    },
                },
            },
        };
        expect(wrapper.vm.getExplainBreakdown(boostOnly)).toBeNull();
    });

    it('should not include boost or cross-entity clauses in the core relevance breakdown', async () => {
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

    it('should translate known field labels and fall back to the raw field otherwise', async () => {
        // known fields resolve to the same snippet the search-config table uses
        expect(wrapper.vm.fieldLabel('name')).toBe('sw-settings-search.generalTab.configFields.name');
        expect(wrapper.vm.fieldLabel('customSearchKeywords')).toBe(
            'sw-settings-search.generalTab.configFields.customSearchKeywords',
        );
        expect(wrapper.vm.fieldLabel('manufacturer.name')).toBe(
            'sw-settings-search.generalTab.configFields.manufacturerName',
        );
        // unknown fields (custom fields, boost rule names) fall back to the raw value
        expect(wrapper.vm.fieldLabel('customFields.material')).toBe('customFields.material');
    });

    it('marks a single typeless-signal row as flat (AdvancedSearch boost / cross-search rows)', async () => {
        expect(wrapper.vm.isFlatRow({ signals: [{ type: null, score: '99' }] })).toBe(true);
        expect(wrapper.vm.isFlatRow({ signals: [{ type: 'exact', score: '99' }] })).toBe(false);
        expect(
            wrapper.vm.isFlatRow({
                signals: [
                    { type: null, score: '99' },
                    { type: null, score: '1' },
                ],
            }),
        ).toBe(false);
    });

    it('renders the panel from the item prop', async () => {
        wrapper = await createWrapper({
            item: {
                name: 'Durable Copper',
                extensions: {
                    search: {
                        _score: 1960,
                        matched_queries: {
                            [JSON.stringify({ field: 'name', term: 'copper', type: 'exact', ranking: 700 })]: 2.8,
                        },
                    },
                },
            },
        });

        expect(wrapper.find('.sw-settings-search-live-search__explain').exists()).toBe(true);
        expect(wrapper.find('.sw-settings-search-live-search__explain-title').text()).toContain('Durable Copper');
        expect(wrapper.find('.sw-settings-search-live-search__explain-row-label').text()).toBe(
            'sw-settings-search.generalTab.configFields.name',
        );
        expect(wrapper.find('.sw-settings-search-live-search__explain-signal-score').text()).toBe('1960.0');
    });

    it('renders nothing when the item has no explainable breakdown', async () => {
        wrapper = await createWrapper({
            item: { extensions: { search: { _score: 42 } } },
        });

        expect(wrapper.find('.sw-settings-search-live-search__explain').exists()).toBe(false);
    });
});
