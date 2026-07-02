/**
 * @sw-package inventory
 */
import template from './sw-settings-search-live-search.html.twig';
import './sw-settings-search-live-search.scss';
import '../sw-settings-search-live-search-keyword';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'liveSearchService',
    ],

    emits: [
        'live-search-results-change',
        'sales-channel-change',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        currentSalesChannelId: {
            type: String,
            required: false,
            default: null,
        },

        searchTerms: {
            type: String,
            required: false,
            default: null,
        },

        searchResults: {
            type: Object,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            liveSearchTerm: '',
            salesChannels: [],
            salesChannelId: this.currentSalesChannelId,
            productSortings: [],
            productSortingKey: null,
            liveSearchResults: null,
            searchInProgress: false,
            showExampleModal: false,
            selectedExplainId: null,
        };
    },

    computed: {
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        productSortingRepository() {
            return this.repositoryFactory.create('product_sorting');
        },

        isSearchEnable() {
            return this.salesChannelId !== null;
        },

        searchColumns() {
            return [
                {
                    property: 'name',
                    label: this.$t('sw-settings-search.liveSearchTab.labelName'),
                    rawData: true,
                },
                {
                    property: 'score',
                    label: this.$t('sw-settings-search.liveSearchTab.labelScore'),
                    rawData: true,
                },
            ];
        },

        products() {
            return this.liveSearchResults && this.liveSearchResults.elements;
        },

        /**
         * The list of result rows the explain helpers operate on. Core renders
         * `products`; the AdvancedSearch override sources its grid from a
         * different list and overrides this getter accordingly.
         */
        resultItems() {
            return this.products;
        },

        /**
         * How many results precede the page currently shown in the grid, so the
         * rank keeps counting across pages. Core loads the results and paginates
         * client-side (nothing precedes → 0); the AdvancedSearch override
         * paginates server-side and overrides this with its page offset.
         */
        resultOffset() {
            return 0;
        },

        topScore() {
            if (!this.resultItems || !this.resultItems.length) {
                return 0;
            }

            return this.resultItems.reduce((max, product) => {
                return Math.max(max, this.getScoreValue(product));
            }, 0);
        },

        selectedExplainItem() {
            if (this.selectedExplainId === null || !this.resultItems) {
                return null;
            }

            return this.resultItems.find((product) => this.explainKey(product) === this.selectedExplainId) ?? null;
        },

        selectedExplainBreakdown() {
            if (!this.selectedExplainItem) {
                return null;
            }

            return this.getExplainBreakdown(this.selectedExplainItem);
        },

        selectedExplainName() {
            const item = this.selectedExplainItem;

            if (!item) {
                return '';
            }

            return item.translated?.name ?? item.name ?? '';
        },

        scoresAreUniform() {
            if (!this.resultItems || this.resultItems.length < 2) {
                return false;
            }

            const first = this.getScoreValue(this.resultItems[0]);

            return this.resultItems.every((product) => this.getScoreValue(product) === first);
        },

        productSortingCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('active', true));
            criteria.addSorting(Criteria.sort('priority', 'DESC'));
            return criteria;
        },

        searchParams() {
            const params = {
                salesChannelId: this.salesChannelId,
                search: this.liveSearchTerm,
            };

            if (this.productSortingKey) {
                params.order = this.productSortingKey;
            }

            return params;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.fetchSalesChannels();
            this.fetchProductSortings();
            this.liveSearchTerm = this.searchTerms;
            this.liveSearchResults = this.searchResults;
        },

        searchOnStorefront() {
            if (!this.liveSearchTerm.length) {
                return;
            }

            // A new search replaces the result set — close any open explain panel
            // so it can't point at a stale row.
            this.selectedExplainId = null;
            this.searchInProgress = true;

            this.liveSearchService
                .search(this.searchParams, {}, {}, { 'sw-language-id': Shopware.Context.api.languageId })
                .then((data) => {
                    this.liveSearchResults = data.data;
                    this.searchInProgress = false;
                    this.$emit('live-search-results-change', {
                        searchTerms: this.liveSearchTerm,
                        searchResults: this.liveSearchResults,
                    });
                })
                .catch((error) => {
                    const message =
                        error.response.status === 500
                            ? this.$t('sw-settings-search.notification.notSupportedLanguageError')
                            : error.message;

                    this.createNotificationError({
                        message,
                    });
                })
                .finally(() => {
                    this.searchInProgress = false;
                });
        },

        fetchSalesChannels() {
            this.salesChannelRepository.search(new Criteria(1, 25)).then((response) => {
                this.salesChannels = response;
            });
        },

        fetchProductSortings() {
            this.productSortingRepository.search(this.productSortingCriteria).then((response) => {
                this.productSortings = response;
                const topSearchSorting = this.productSortings.find((entry) => entry.key === 'score');

                if (topSearchSorting) {
                    this.productSortingKey = topSearchSorting.key;
                }
            });
        },

        changeSalesChannel(salesChannelId) {
            this.salesChannelId = salesChannelId;
            this.$emit('sales-channel-change', salesChannelId);
        },

        onShowExampleModal() {
            this.showExampleModal = true;
        },

        onCloseExampleModal() {
            this.showExampleModal = false;
        },

        explainKey(item) {
            return item.id ?? item.extensions?.search?._score ?? null;
        },

        getScoreValue(item) {
            return parseFloat(item?.extensions?.search?._score) || 0;
        },

        /**
         * Score is no longer rounded to an integer — that hid differences
         * between near-identical scores. Whole numbers stay whole; fractional
         * scores keep one decimal.
         */
        formatScore(value) {
            const score = parseFloat(value) || 0;

            return Number.isInteger(score) ? `${score}` : score.toFixed(1);
        },

        getScoreBarWidth(item) {
            if (!this.topScore) {
                return '0%';
            }

            return `${Math.max(2, (this.getScoreValue(item) / this.topScore) * 100)}%`;
        },

        getRank(item) {
            if (!this.resultItems) {
                return null;
            }

            const index = this.resultItems.indexOf(item);

            if (index === -1) {
                return null;
            }

            return this.resultOffset + index + 1;
        },

        hasExplain(item) {
            return Boolean(item?.extensions?.search?.matched_queries);
        },

        toggleExplain(item) {
            const key = this.explainKey(item);
            this.selectedExplainId = this.selectedExplainId === key ? null : key;
        },

        isExplainOpen(item) {
            return this.selectedExplainId !== null && this.selectedExplainId === this.explainKey(item);
        },

        /**
         * Structured relevance breakdown for the explain panel. Returns `null`
         * when the search engine provided no `matched_queries` (e.g. a
         * non-Elasticsearch / MySQL search), so the panel is skipped entirely.
         * The AdvancedSearch extension appends its own sections via `$super`.
         */
        getExplainBreakdown(item) {
            const matchedQueries = item?.extensions?.search?.matched_queries;

            if (!matchedQueries) {
                return null;
            }

            const name = item?.translated?.name ?? item?.name ?? '';
            const rows = this.toSignalRows(this.collectFieldRows(matchedQueries), name);

            if (!rows.length) {
                return null;
            }

            return {
                total: this.getScoreValue(item),
                sections: [
                    {
                        label: this.$t('sw-settings-search.liveSearchTab.relevance'),
                        rows,
                    },
                ],
            };
        },

        /**
         * Turns the per-clause `matched_queries` into field rows, each keeping
         * ONE signal per search term — the MOST SPECIFIC match type that fired
         * for it (exact > prefix > fuzzy > partial), not the highest-scoring one.
         * A word that matches exactly is also trivially a prefix / fuzzy / ngram
         * match of itself, and the `partial` (ngram) clause tends to out-score
         * the others (especially at a low `min_gram`), so picking by score would
         * mislabel almost everything as `partial` and hide real prefix / fuzzy
         * matches. Picking by specificity keeps the label meaningful; `partial`
         * only wins when it is the ONLY way a term matched (a true fragment hit,
         * whose shared fragment is explained in `toSignalRows`).
         */
        collectFieldRows(matchedQueries) {
            const groups = new Map();

            Object.keys(matchedQueries).forEach((matchedQuery) => {
                let parsedQuery;

                try {
                    parsedQuery = JSON.parse(matchedQuery);
                } catch {
                    return;
                }

                // Boost / cross-entity clauses are explained by the AdvancedSearch extension.
                if (parsedQuery.boost || parsedQuery.crossEntity) {
                    return;
                }

                const label = this.humanizeField(parsedQuery.field);
                const score = parseFloat(matchedQueries[matchedQuery]) || 0;

                if (!groups.has(label)) {
                    groups.set(label, { label, ranking: parsedQuery.ranking ?? null, signals: new Map() });
                }

                const group = groups.get(label);

                if (parsedQuery.ranking !== null && parsedQuery.ranking !== undefined) {
                    group.ranking = Math.max(group.ranking ?? 0, parsedQuery.ranking);
                }

                // Key by term (falling back to type) so each search word keeps
                // only its most representative match type.
                const signalKey = parsedQuery.term ?? parsedQuery.type ?? '';
                const candidate = { type: parsedQuery.type ?? null, term: parsedQuery.term ?? null, score };
                const existing = group.signals.get(signalKey);

                if (!existing || this.isMoreSpecificSignal(candidate, existing)) {
                    group.signals.set(signalKey, candidate);
                }
            });

            return [...groups.values()].map((group) => ({
                label: group.label,
                ranking: group.ranking,
                signals: [...group.signals.values()],
            }));
        },

        /**
         * Specificity ranking of match types for display: a whole-phrase match is
         * the strongest statement, then exact word, prefix (starts-with), fuzzy
         * (typo), and finally partial (shared fragment). Used to pick which type
         * represents a term when several clauses fired for it — e.g. a multi-word
         * term matches both `phrase` (match_phrase) and `prefix`
         * (match_phrase_prefix); `phrase` must win so its (boosted) score shows.
         * Unknown types sort last.
         */
        matchTypeRank(type) {
            return { phrase: 0, exact: 1, prefix: 2, fuzzy: 3, ngram: 4 }[type] ?? 5;
        },

        isMoreSpecificSignal(candidate, existing) {
            const candidateRank = this.matchTypeRank(candidate.type);
            const existingRank = this.matchTypeRank(existing.type);

            if (candidateRank !== existingRank) {
                return candidateRank < existingRank;
            }

            return candidate.score > existing.score;
        },

        /**
         * Turns field/boost/cross rows into panel rows. Every clause bar is
         * scaled to the single strongest clause across the whole breakdown, so
         * bars are comparable between fields; the raw Elasticsearch match score
         * is shown per clause. Rows and their clauses are ordered strongest
         * first. Deliberately NOT a "% of total" — Elasticsearch keeps the
         * strongest clause plus a fraction of the rest and then applies the
         * field weight, so clause scores do not sum to `_score`. Shared by the
         * AdvancedSearch override for its boosting / cross-search sections.
         *
         * `fieldText` (the result's name) is used to explain `partial` (ngram)
         * matches, which hit on a shared letter fragment rather than the whole
         * word — e.g. "copper" matching "Paper" via "per".
         */
        toSignalRows(rows, fieldText = '') {
            const max = rows
                .flatMap((row) => row.signals)
                .reduce((m, signal) => Math.max(m, signal.score), 0) || 1;

            return rows
                .map((row) => ({
                    label: row.label,
                    ranking: row.ranking ?? null,
                    top: row.signals.reduce((m, signal) => Math.max(m, signal.score), 0),
                    signals: [...row.signals]
                        .sort((a, b) => b.score - a.score)
                        .map((signal) => {
                            // A multi-word term comes from the whole-phrase query
                            // (`ProductSearchQueryBuilder` also searches the full search
                            // string, not only its individual words). Present it as a
                            // `phrase` match — the underlying clause is a phrase-prefix,
                            // but "phrase" is what it means to a merchant, and the
                            // single-word fragment hint below would be misleading for it.
                            const isPhrase = (signal.term ?? '').includes(' ');

                            return {
                                type: isPhrase ? 'phrase' : (signal.type ?? null),
                                term: signal.term ?? null,
                                score: this.formatScore(signal.score),
                                barWidth: `${Math.max(2, (signal.score / max) * 100)}%`,
                                // Point a single-word partial / prefix match at the word it
                                // hit (name field only — that's the text we have on the
                                // result). Exact / fuzzy / phrase are self-explanatory.
                                context: !isPhrase && ['ngram', 'prefix'].includes(signal.type) && row.label === 'name'
                                    ? this.matchedFragment(signal.term, fieldText)
                                    : null,
                            };
                        }),
                }))
                .sort((a, b) => b.top - a.top)
                .map(({ top, ...row }) => row);
        },

        /**
         * Finds the longest letter fragment (>= 3 chars, the ngram floor) that a
         * search term shares with a word in the given text, so a `partial` match
         * can be explained as e.g. `“per” in “Paper”`. Returns null when nothing
         * meaningful overlaps.
         */
        matchedFragment(term, text) {
            if (!term || !text) {
                return null;
            }

            const needle = term.toLowerCase();
            let best = { fragment: '', word: '' };

            text.split(/\s+/).filter(Boolean).forEach((word) => {
                const fragment = this.longestCommonSubstring(needle, word.toLowerCase());

                if (fragment.length > best.fragment.length) {
                    best = { fragment, word };
                }
            });

            if (best.fragment.length < 3) {
                return null;
            }

            // `whole` = the entire search term appears in the word (e.g. "awe" in
            // "Awesome"), so the UI can say `in "Awesome"` rather than repeating
            // the term as `"awe" in "Awesome"`.
            return { ...best, whole: best.fragment === needle };
        },

        longestCommonSubstring(a, b) {
            let best = '';

            for (let i = 0; i < a.length; i += 1) {
                for (let j = i + best.length + 1; j <= a.length; j += 1) {
                    const candidate = a.slice(i, j);

                    if (b.includes(candidate)) {
                        best = candidate;
                    }
                }
            }

            return best;
        },

        /**
         * Strips Elasticsearch internals from a resolved field name so the panel
         * shows the field a merchant configured: the language id translated
         * fields are indexed under (`name.<uuid>` → `name`) and the analyzer
         * subfields (`.search` / `.exact` / `.ngram`).
         */
        humanizeField(field) {
            if (!field) {
                return '';
            }

            return field
                .split('.')
                .filter((segment) => !/^[0-9a-f]{32}$/i.test(segment))
                .filter((segment) => !['search', 'exact', 'ngram'].includes(segment))
                .join('.');
        },

        explainTypeLabel(type) {
            if (!type) {
                return '';
            }

            return this.$t(`sw-settings-search.liveSearchTab.matchType.${type}`);
        },

        explainTypeTooltip(type) {
            if (!type) {
                return '';
            }

            return this.$t(`sw-settings-search.liveSearchTab.matchTypeTooltip.${type}`);
        },

        /**
         * Translates a resolved field name to the same human label the search
         * configuration table uses (`generalTab.configFields.*`). Falls back to
         * the raw field when there is no snippet — e.g. a custom field like
         * `customFields.material`, or the AdvancedSearch boost/cross-entity rows.
         */
        fieldLabel(field) {
            const snippetKey = {
                name: 'name',
                'parent.name': 'parentName',
                description: 'description',
                productNumber: 'productNumber',
                manufacturerNumber: 'manufacturerNumber',
                ean: 'ean',
                customSearchKeywords: 'customSearchKeywords',
                'manufacturer.name': 'manufacturerName',
                'manufacturer.customFields': 'manufacturerCustomFields',
                'categories.name': 'categoriesName',
                'categories.customFields': 'categoriesCustomFields',
                'tags.name': 'tagsName',
                metaTitle: 'metaTitle',
                metaDescription: 'metaDescription',
                'properties.name': 'propertiesValue',
                'options.name': 'variantValue',
            }[field];

            return snippetKey ? this.$t(`sw-settings-search.generalTab.configFields.${snippetKey}`) : field;
        },
    },
};
