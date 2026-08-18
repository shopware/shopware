/**
 * @sw-package inventory
 */
import template from './sw-settings-search-live-search-explain.html.twig';
import './sw-settings-search-live-search-explain.scss';
import { parseClauses, isFieldClause } from '../../helper/explain.helper';
import { SEARCH_CONFIG_FIELD_SNIPPETS } from '../../constant/search-config-fields.constant';

/**
 * The "Why this ranking?" breakdown for one live-search result row. The parent
 * owns the grid and which row is expanded; the AdvancedSearch extension
 * overrides `getExplainBreakdown` here to add its boosting / cross-search
 * sections.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        item: {
            type: Object,
            required: true,
        },

        // The term the displayed results were actually searched for.
        searchTerm: {
            type: String,
            required: false,
            default: '',
        },

        // All results share one score — the order is a tie, and the panel says so.
        scoresAreUniform: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        breakdown() {
            return this.getExplainBreakdown(this.item);
        },

        explainName() {
            return this.item.translated?.name ?? this.item.name ?? '';
        },
    },

    methods: {
        getScoreValue(item) {
            return parseFloat(item?.extensions?.search?._score) || 0;
        },

        formatScore(value) {
            const score = parseFloat(value) || 0;

            return Number.isInteger(score) ? `${score}` : score.toFixed(1);
        },

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
                terms: this.termCoverage(matchedQueries),
                sections: [
                    {
                        label: this.$t('sw-settings-search.liveSearchTab.relevance'),
                        rows,
                    },
                ],
            };
        },

        termCoverage(matchedQueries) {
            // Mirror the backend tokenizer: words below the minimum search length (2)
            // and pure punctuation are never queried, so don't report them as unmatched.
            // (Per-language excluded terms are unknown here — a known limit.)
            const words = this.searchTerm
                .toLowerCase()
                .split(/\s+/)
                .filter((word) => word.length >= 2 && /[\p{L}\p{N}]/u.test(word));

            if (words.length < 2) {
                return null;
            }

            // Whole-word equality — "iron" must not count "on" as matched; a
            // phrase term ("paper rippers") covers each of its words.
            const matchedWords = new Set(
                parseClauses(matchedQueries).flatMap(({ parsed }) =>
                    (parsed.term ?? '').toLowerCase().split(/\s+/).filter(Boolean),
                ),
            );

            const matched = words.filter((word) => matchedWords.has(word));
            const missed = words.filter((word) => !matchedWords.has(word));

            return { matched, missed };
        },

        /**
         * Groups `matched_queries` into field rows, keeping ONE signal per search
         * term: the MOST SPECIFIC match type that fired (exact > prefix > fuzzy >
         * partial), not the highest-scoring — an exact hit is trivially also an
         * (often higher-scoring) ngram hit, and would otherwise mislabel as partial.
         */
        collectFieldRows(matchedQueries) {
            const groups = new Map();

            parseClauses(matchedQueries).forEach(({ parsed: parsedQuery, score: rawScore }) => {
                // Boost / cross-entity clauses are explained by the AdvancedSearch extension.
                if (!isFieldClause(parsedQuery)) {
                    return;
                }

                const label = this.humanizeField(parsedQuery.field);

                // Weighted clause scores already include the field ranking; scale the
                // raw per-clause (text field) ones so all bars share one footing.
                const ranking = parsedQuery.ranking ?? 1;
                const score = parsedQuery.weighted ? rawScore : rawScore * ranking;

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

        // Display specificity of match types, strongest statement first;
        // unknown (plugin-supplied) types sort last.
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
         * Turns field/boost/cross rows into panel rows, ordered strongest first,
         * bars scaled to the strongest clause overall. Deliberately NOT a "% of
         * total": clause scores do not sum to `_score`. Shared by the AdvancedSearch
         * override; `fieldText` (the result's name) feeds the fragment hints.
         */
        toSignalRows(rows, fieldText = '') {
            const max = rows.flatMap((row) => row.signals).reduce((m, signal) => Math.max(m, signal.score), 0) || 1;

            return rows
                .map((row) => ({
                    label: row.label,
                    ranking: row.ranking ?? null,
                    top: row.signals.reduce((m, signal) => Math.max(m, signal.score), 0),
                    signals: [...row.signals]
                        .sort((a, b) => b.score - a.score)
                        // Types are taken as the backend reports them — no re-labelling.
                        .map((signal) => ({
                            type: signal.type ?? null,
                            term: signal.term ?? null,
                            score: this.formatScore(signal.score),
                            barWidth: `${Math.max(2, (signal.score / max) * 100)}%`,
                            // Only partial/prefix need a hint, and only the name's text is at hand.
                            context:
                                [
                                    'ngram',
                                    'prefix',
                                ].includes(signal.type) && row.label === 'name'
                                    ? this.matchedFragment(signal.term, fieldText, signal.type)
                                    : null,
                        })),
                }))
                .sort((a, b) => b.top - a.top)
                .map(({ top, ...row }) => row);
        },

        // A single typeless signal (AdvancedSearch boost / cross-search rows)
        // fits on one line — name, bar and score, no per-type rows.
        isFlatRow(row) {
            return row.signals.length === 1 && !row.signals[0].type;
        },

        // Explains where a prefix/partial match hit within the text,
        // e.g. `“atter” in “Swatter”`; null when nothing meaningful overlaps.
        matchedFragment(term, text, type = 'ngram') {
            if (!term || !text) {
                return null;
            }

            const needle = this.foldTerm(term);
            const words = text.split(/\s+/).filter(Boolean);

            // Prefix = a word STARTS with the term; a shared-fragment guess could
            // pick "box" out of "Xbox" although the clause fired on "Boxer".
            if (type === 'prefix') {
                const word = words.find((candidate) => this.foldTerm(candidate).startsWith(needle));

                return word ? { fragment: term, word, whole: true } : null;
            }

            let best = { fragment: '', word: '' };

            words.forEach((word) => {
                const fragment = this.longestCommonSubstring(needle, this.foldTerm(word));

                if (fragment.length > best.fragment.length) {
                    best = { fragment, word };
                }
            });

            // Below the ngram floor (SHOPWARE_ES_NGRAM_MIN_GRAM, default 4) the
            // analyzer cannot have matched — show no hint rather than a wrong one.
            if (best.fragment.length < 4) {
                return null;
            }

            // whole = the entire term appears in the word, so the UI can drop the fragment.
            return { ...best, whole: best.fragment === needle };
        },

        // Lowercase + ascii-fold like the search analyzer (ü → u, ß → ss), so
        // the fragment comparison sees the text Elasticsearch matched on.
        foldTerm(value) {
            return value
                .toLowerCase()
                .replace(/ß/g, 'ss')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '');
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

        humanizeField(field) {
            if (!field) {
                return '';
            }

            return field
                .split('.')
                .filter((segment) => !/^[0-9a-f]{32}$/i.test(segment))
                .filter(
                    (segment) =>
                        ![
                            'search',
                            'exact',
                            'ngram',
                        ].includes(segment),
                )
                .join('.');
        },

        // Unknown (plugin-supplied) match types fall back to the raw type
        // instead of leaking the snippet key.
        explainTypeLabel(type) {
            if (!type) {
                return '';
            }

            const snippetKey = `sw-settings-search.liveSearchTab.matchType.${type}`;
            const label = this.$t(snippetKey);

            return label === snippetKey ? type : label;
        },

        explainTypeTooltip(type) {
            if (!type) {
                return '';
            }

            const snippetKey = `sw-settings-search.liveSearchTab.matchTypeTooltip.${type}`;
            const tooltip = this.$t(snippetKey);

            return tooltip === snippetKey ? type : tooltip;
        },

        fieldLabel(field) {
            const snippetKey = SEARCH_CONFIG_FIELD_SNIPPETS[field];

            return snippetKey ? this.$t(`sw-settings-search.generalTab.configFields.${snippetKey}`) : field;
        },
    },
};
