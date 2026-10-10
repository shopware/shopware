# Elasticsearch search — architecture

This document explains how a search term flows from a storefront request to a ranked
list of products, and how the Elasticsearch bundle's primitives (analyzers, field
configs, query builders) compose to produce that result.

It exists because the relevant logic is spread across ~10 classes, two repos
(`shopware/shopware` plus the AdvancedSearch plugin in `shopware/SwagCommercial`),
several Symfony YAML files, and a half-dozen Elasticsearch analyzer chains. New
contributors and future maintainers need a single map. So does anyone (human or
agent) investigating "why did *this* product rank above *that one* for the term X?".

> Living spec: `tests/integration/Elasticsearch/Product/SearchCasesTest.php` is the
> executable mirror of the rules described here. If a behaviour change in this doc
> doesn't have a matching scenario in that test, one of the two is wrong.

## 1. End-to-end pipeline

```
┌─────────────────────────────────────────────────────────────────┐
│  Storefront request  →  ProductSearchBuilder                    │
│   (or AdvancedSearch plugin's ProductSearchRouteDecorator       │
│    when the ADVANCED_SEARCH-3068620 license is active)          │
├─────────────────────────────────────────────────────────────────┤
│  ElasticsearchTokenizer                                         │
│    whitespace split + min-length + alphanumeric guard           │
│    (no preserved_chars; analyzers handle separators)            │
│  + StopwordTokenFilter                                          │
├─────────────────────────────────────────────────────────────────┤
│  SearchConfigLoader  →  list<SearchFieldConfig>                 │
│    one per merchant-configured search field, carrying           │
│    {ranking, useExactSubfield, tokenize, andLogic, …}           │
├─────────────────────────────────────────────────────────────────┤
│  TokenQueryBuilder  →  per-field FieldQueryBuilder              │
│    For every (token, field) pair, build:                        │
│    ┌─exact-subfield TermQuery (boost ×2)  if useExactSubfield─┐ │
│    ├─fuzzy MatchQuery on .search                              │ │
│    ├─prefix MatchQuery on .search                             │ │
│    └─ngram MatchQuery on .ngram                               │ │
│                              ↓ DisMax (tie_breaker 0.2)         │
│  Combine across fields and tokens via BoolQuery (and/should).   │
├─────────────────────────────────────────────────────────────────┤
│  Index mapping: KEYWORD_FIELD + buildTextFieldConfig(…)         │
│    flag combinations:                                           │
│      withExact       → adds .exact subfield (whitespace, no     │
│                        norms) for high-boost literal-token hits │
│      technicalTerms  → routes .search through the               │
│                        technical-term analyzer chain            │
│      lengthNorm      → applies sw_length_norm BM25 to .search   │
│                        for fields where doc length is signal    │
├─────────────────────────────────────────────────────────────────┤
│  Analyzer chains                                                │
│    sw_whitespace_analyzer       — lowercase, no splitting       │
│    sw_{english,german}_analyzer — language stemmer + stopwords  │
│    sw_*_technical_term_*        — word_delimiter_graph chain    │
│    sw_ngram_analyzer            — ngram filter (3-grams default)│
├─────────────────────────────────────────────────────────────────┤
│  BM25                                                           │
│    similarity=default        b=0    (no length norm)            │
│    similarity=sw_length_norm b=0.75 (standard BM25)             │
│  Optional cutoff: core.search.minScore drops trailing hits      │
└─────────────────────────────────────────────────────────────────┘
```

## 2. Field types

`AbstractElasticsearchDefinition` defines the building blocks every concrete
ES definition (Product, Category, etc.) composes its mapping from:

| Constant / helper | What it produces | When to use |
|---|---|---|
| `KEYWORD_FIELD` | `type: keyword`, `ignore_above: 10000`, lowercase normalizer | Identifiers, IDs, exact-match-only fields |
| `BOOLEAN_FIELD`, `INT_FIELD`, `FLOAT_FIELD` | scalar mappings | Self-explanatory |
| `SEARCH_FIELD` | `.search` (whitespace) + `.ngram` subfields | Default for searchable text |
| `SEARCH_FIELD_WITH_EXACT` | adds `.exact` (whitespace, `norms: false`) on top of `SEARCH_FIELD` | Identifier fields where the literal token must win the high-boost lane |
| `TECHNICAL_TERM_SEARCH_FIELD` | `.search` via `sw_*_technical_term_*` instead of plain whitespace | SKU-style fields where `BC1010` ↔ `BC 1010`, `M8x20`, `5,5` need to map onto each other |
| `buildTextFieldConfig(withExact?, technicalTerms?, lengthNorm?)` | composable wrapper around the above; **preferred over the consts** | New mappings — keeps configuration declarative |

The deprecated `getTextFieldConfig()` (no flags) remains as `KEYWORD_FIELD + SEARCH_FIELD` for plugins that decorated `AbstractElasticsearchDefinition`. New code uses `buildTextFieldConfig()` exclusively.

### Decision table for `buildTextFieldConfig`

| Field example | Flags | Why |
|---|---|---|
| `name` | `buildTextFieldConfig(withExact: true, technicalTerms: true)` | Literal-token wins (`BC1010`) AND technical decomposition (`BC-1010` → `BC 1010`) |
| `customSearchKeywords` | `buildTextFieldConfig(withExact: true, technicalTerms: true, lengthNorm: true)` | Same as `name` plus length normalisation — long curated lists shouldn't win on TF alone |
| `productNumber`, `ean`, `manufacturerNumber` | `buildTextFieldConfig(withExact: true, technicalTerms: true)` | Same as `name`. The exact subfield is what stops UUIDs and other auto-generated identifiers from phantom-matching numeric subfragments |
| `description`, `metaDescription` | `buildTextFieldConfig(lengthNorm: true)` | Long-form prose; standard BM25 favours documents with concentrated term frequency |
| `metaTitle`, category/manufacturer names | `buildTextFieldConfig()` (defaults) | Short text; defaults are fine |
| `manufacturerId`, `streamIds`, etc. | `KEYWORD_FIELD` | Pure identifiers, never tokenised |

## 3. Analyzer chains

Three families. All are defined in `src/Elasticsearch/Resources/config/packages/elasticsearch.yaml` under `elasticsearch.analysis.analyzer`.

The analyzer names referenced from PHP code (`AbstractElasticsearchDefinition`, `FieldQueryBuilder`, plugin code) are exposed as class constants on `ElasticsearchFieldBuilder` — `ANALYZER_WHITESPACE`, `ANALYZER_NGRAM`, `ANALYZER_WHITESPACE_TECHNICAL_INDEX`, `ANALYZER_WHITESPACE_TECHNICAL_SEARCH`, `NORMALIZER_LOWERCASE`, `SIMILARITY_LENGTH_NORM`. Use the constants instead of literal strings; they're the canonical names and they cross-reference the YAML.

### 3.1 Plain whitespace — `sw_whitespace_analyzer`

```
tokenizer: whitespace
filter:    [lowercase]
```

Used by `.exact` subfields. No splitting beyond whitespace, no decomposition. The point is to preserve the literal token so `TermQuery(field.exact, "5,5")` against an indexed value of `"… 5,5 …"` matches and `… 55 …` does not. This is the high-boost lane.

### 3.2 Language analyzers — `sw_english_analyzer`, `sw_german_analyzer`

```
tokenizer: whitespace
filter:    [lowercase, sw_{lang}_stop_filter]
```

Translated language sub-fields under translated text fields (`name.lang_en.search`, `name.lang_de.search`, …). They drop stopwords for the active language. No stemming today; we may add it but it's a separate decision.

### 3.3 Technical-term — `sw_{lang|whitespace}_technical_term_{index|search}_analyzer`

Used by `name`, `productNumber`, `ean`, `manufacturerNumber`, `customSearchKeywords` (when `technicalTerms: true`). The chain is asymmetric on purpose:

```
index:   [char_filter*] → word_delimiter_graph → flatten_graph → lowercase → sw_length_min → sw_decimal_normalize_token → remove_duplicates → [stop]
search:  [char_filter*] → word_delimiter_graph                 → lowercase → sw_length_min → sw_decimal_normalize_token → remove_duplicates → [stop] → sw_unique_filter
```

`[char_filter*]` is the locale-and-feature-driven char_filter list described in §3.3.2.
`[stop]` is `sw_english_stop_filter` / `sw_german_stop_filter` (only on the language variants, not the locale-agnostic `sw_whitespace_*` chain).

#### 3.3.1 `sw_word_delimiter_filter`

```yaml
type: word_delimiter_graph
preserve_original: true     # keep `BC-1010-XL` as-is
catenate_all: true          # also emit `BC1010XL` (joins every sub-part)
catenate_words: true        # …`BCXL` (joins consecutive word sub-parts)
catenate_numbers: true      # …`33` from `3.3` (joins consecutive number sub-parts)
split_on_case_change: true  # `LaserJet` → laser, jet (helps brand search)
generate_word_parts: true   # `BC-1010-XL` → BC, 1010, XL
split_on_numerics: true     # `BC1010` → BC, 1010
```

`catenate_numbers` only triggers when an input token has multiple numeric sub-parts separated by non-alphanumeric characters — typically a `sw_unit_glue` output like `3.3mm` (sub-parts `3`, `3`, `mm`) where `catenate_numbers` recovers the standalone `33`. Hex / SKU content (`FF0000`, `BC-1010-XL`) usually has at most one numeric run so this flag is a no-op there.

#### 3.3.2 Pre-tokenization char_filters

Char_filters operate on the raw string before the whitespace tokenizer; they normalize notational variants so the same dimensional or decimal value produces identical token streams whether merchants write it one way or another.

| Filter | Pattern → replacement | Wired into | Toggle |
|---|---|---|---|
| `sw_decimal_normalize` | `(\d),(\d)` → `$1.$2` | `sw_german_technical_term_{index,search}_analyzer` only | always on (locale correctness — `,` is the German decimal separator; never wired into English / locale-agnostic chains where `,` is the thousands separator) |
| `sw_dimension_normalize` | `(\d)\s*[xX×]\s*(\d)` → `$1x$2` | all six technical-term analyzers (whitespace + en + de × index/search) | env-gated, off by default — `SHOPWARE_ES_DIMENSION_NORMALIZE=1` enables; toggling requires reindex; injection happens in `IndexCreator` so the bundled YAML stays a single canonical settings document |
| `sw_unit_glue` | `(^\|\s)(\d+(?:[./,'-]\d+)*)\s+([^\d\s])` → `$1$2$3` | all six technical-term analyzers | always on; bridges a *complete numeric run* followed by a unit/symbol token. Covers `100 ml` ↔ `100ml`, `3.3 mm` ↔ `3.3mm`, `5 €` ↔ `5€`, `64 GB` ↔ `64GB`, `100 °C` ↔ `100°C`, `1,000 kg` ↔ `1,000kg`, `1'200 km` ↔ `1'200km` (Swiss thousands), `1/2 inch` ↔ `1/2inch` (fractions), `5-10 mm` ↔ `5-10mm` (ranges). Three structural guards: `(^\|\s)` requires the numeric run to start at a word boundary so embedded digits (the `49` in `Gr49`) don't trigger glue; `[./,'-]` between digit groups admits decimal / thousands / fraction / range separators without admitting arbitrary punctuation; `[^\d\s]` second capture excludes digits so `Pack 5 of 10` is not glued. After this filter, `word_delimiter_graph` re-splits the merged form via `split_on_numerics` and re-joins via `catenate_all`, so both the glued and split forms end up in the inverted index. Literal-form queries are independently served by `.exact` (whitespace-only analyzer), which sees the verbatim tokens regardless of what this filter does to `.search`. |

#### 3.3.3 Cleanup and convergence filters

- `sw_length_min { type: length, min: 2 }` — drops single-character noise tokens like the `i` in `iPhone` after case-split, the `c` and `a` from `…c55a…` in a UUID, etc.
- `sw_decimal_normalize_token { type: pattern_capture, preserve_original: true, patterns: ['(\d+)\.0+(?=\D|$)', '(\d+\.\d*[1-9])0+(?=\D|$)'] }` — emits canonical trailing-zero-stripped forms as additional sibling tokens alongside the originals. Two patterns: the first (`(\d+)\.0+`) handles pure-zero fractions (`5.0 ↔ 5.00 ↔ 5.000` all emit `5`); the second (`(\d+\.\d*[1-9])0+`) handles non-zero fractions with trailing zeros (`3.30 → 3.3`, `5.150 → 5.15`, `1.0500 → 1.05`). Operates on `word_delimiter_graph` output including welded forms, so `5.0mm → 5` and `3.30mm → 3.3` both work. Positioned **after** `sw_length_min` so single-digit captures aren't reaped by the length floor (which only applies to the filter's input, not its output). `preserve_original: true` keeps glued/verbatim tokens intact. Doesn't trigger on fractions without trailing zeros (`5.5`, `5.15`), integers (`100mm`), or hex/SKU content (no `\.`) — distinct numeric values keep distinct canonical tokens, so discrimination is preserved.
- `sw_unique_filter { type: unique, only_on_same_position: false }` — **search side only.** De-duplicates cross-position duplicate tokens in the *query* so `bohrcraft bohrcraft` doesn't double-weight `bohrcraft` against any matching doc. Index side keeps positional fidelity for any future phrase / proximity query.

The "bridge the whitespace gap" job (`100 ml` ↔ `100ml`, `3.3 mm` ↔ `3.3mm`) lives entirely in §3.3.2's `sw_unit_glue` char_filter — running before tokenization rather than as a token-level shingle. A shingle filter was tried briefly (operating on adjacent unigrams via `min/max=2`, `token_separator=""`) but produced index bloat from non-numeric pairs (`BohrcraftDIN`) and interacted badly with `word_delimiter_graph` graph alternates; the char_filter approach replaces it with one targeted regex.

### 3.4 N-gram — `sw_ngram_analyzer`

```
tokenizer: whitespace
filter:    [lowercase, sw_ngram_filter]
```

`sw_ngram_filter` is configured via `SHOPWARE_ES_NGRAM_MIN_GRAM` / `SHOPWARE_ES_NGRAM_MAX_GRAM`. Used for the `.ngram` subfield, which catches substring matches (`las` in `laser`) when no other path hits.

## 4. Query construction

### 4.1 `ProductSearchQueryBuilder` — the entry point for storefront product search

1. Lowercase the term.
2. Tokenize via `ElasticsearchTokenizer` (whitespace + min-length + `\p{L}\p{N}` guard — see §5).
3. `TokenFilter::filter` removes stopwords.
4. Load `SearchConfigLoader::load($context)` → array of merchant-configured search fields with `ranking`, `tokenize`, `andLogic`, `useExactSubfield`.
5. For each (token, fieldConfig), call `TokenQueryBuilder::build` → `FieldQueryBuilder` → produce a per-token `DisMaxQuery`.
6. Combine per-token queries with `BoolQuery::MUST` (AND-logic) or `SHOULD` (OR-logic), depending on `andLogic`.
7. If the original term wasn't exactly the joined token list, also build a single-clause query for the original term and `DisMax` it with the per-token version. The `tie_breaker: 0.2` makes the non-winning clause contribute 20% of its score.

### 4.2 `FieldQueryBuilder` — the per-field clause set

For a single (field, token), the builder returns a `DisMax` of up to four clauses:

| Clause | Targets | Boost | When emitted |
|---|---|---|---|
| Exact-subfield term | `field.exact` | 2 | Only when `SearchFieldConfig::useExactSubfield()` is `true` |
| Fuzzy match | `field.search` | 0.4 | Always |
| Prefix match | `field.search` | 0.4 | Single-token queries only |
| N-gram match | `field.ngram` | 0.4 | When the ngram subfield exists |

The DisMax picks the strongest of these per document. The exact-subfield boost of ×2 means: when a literal token matches in the `.exact` subfield, it dominates the dis_max regardless of any other partial path.

`TranslatedFieldQueryBuilder` decorates this for translated fields: it iterates over the language chain (e.g. `[lang_en, lang_de]`), runs the inner builder against each `field.lang_*.…` path, and dis_max'es across languages with a 0.8 ranking decay per fallback step.

### 4.3 Boost / ranking sources

Three independent multipliers stack:

1. **Per-clause boost** inside `FieldQueryBuilder` (×2 for exact, ×0.4 for fuzzy/prefix/ngram). Tunes which path wins the per-(token, field) DisMax.
2. **Per-field ranking** from `SearchFieldConfig::getRanking()`, which the merchant configures in the admin (`product_search_config_field.ranking`). Multiplies the whole per-field score. Typical values: name=700, productNumber=1000, customSearchKeywords=900.
3. **BM25** (see §5).

The ranking values are merchant-tunable; the boosts are code-tunable. When in doubt about a ranking surprise, check both — and use `?explain=true` on the OpenSearch query to see which clause is dominating.

## 5. Tokenization rules (`ElasticsearchTokenizer`)

Storefront and plugin entry points all funnel through `Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchTokenizer`:

```php
final class ElasticsearchTokenizer implements TokenizerInterface
{
    public function tokenize(string $string, ?int $tokenMinimumLength = null): array
    {
        $tokenMinimumLength ??= AbstractTokenFilter::DEFAULT_MIN_SEARCH_TERM_LENGTH;

        return array_values(array_unique(array_filter(
            preg_split('/\s+/u', mb_strtolower($string), -1, \PREG_SPLIT_NO_EMPTY) ?: [],
            static fn (string $token): bool =>
                mb_strlen($token) >= $tokenMinimumLength
                && preg_match('/[\p{L}\p{N}]/u', $token) === 1,
        )));
    }
}
```

Three rules:

1. **Split on whitespace only.** Commas, slashes, hyphens, periods reach the analyzer chain unchanged so technical strings like `5,5`, `M8x20`, `HSS-G` survive.
2. **Min length.** Short tokens like single digits get filtered. The minimum is per-merchant (`product_search_config.min_search_length`).
3. **Alphanumeric guard.** Pure-punctuation tokens (`&%$`, `---`) are rejected outright so they don't reach Elasticsearch.

The DAL/MySQL `Tokenizer` (with `preserved_chars`) is unchanged and unused on the ES path. `preserved_chars` only affects the MySQL fallback search.

## 6. BM25 and minScore

```yaml
similarity:
  default:        { type: BM25, b: 0    }   # no length norm
  sw_length_norm: { type: BM25, b: 0.75 }   # standard BM25
```

- `default` is applied to every text field unless overridden. With `b=0`, BM25 ignores field length, so a one-word `name` and a 50-word `name` score the same on TF/IDF for a single-term match. This is what merchants want for short fields like `name`, `productNumber`, `ean` — otherwise short SKUs dominate just because they have fewer terms.
- `sw_length_norm` is applied to `description`, `metaDescription`, and `customSearchKeywords` (when `lengthNorm: true`). For prose-like fields, BM25's standard length normalization (concentrate term frequency in a short doc → higher score) is the right behavior.

`core.search.minScore` (system_config float, default `0`) sets a floor for `Search::setMinScore`. Values like `5.0` cut the trailing tail of marginal n-gram coincidences without affecting the meaningful results. Set per sales channel.

## 7. AdvancedSearch (plugin) layer

In `shopware/SwagCommercial`, the AdvancedSearch plugin layers on top of the core ES bundle when the `ADVANCED_SEARCH-3068620` license is active:

- `SearchTermExtractor` (storefront entry) and `SearchTermTokenizer` use the same core `ElasticsearchTokenizer` — no plugin-side tokenization rules to keep in sync.
- `DictionaryIndexConfigSubscriber` injects a `dictionary_decompounder` filter into the per-language analyzer chain at index-create time, seeded by ~220 curated German root nouns from `Resources/dictionaries/german_compound_roots.json` (`akkubohrer` → `akku, bohrer`).
- `BoostingQueryBuilder` adds rule-driven score modifiers on top of the dis_max — orthogonal to the matching/relevance pipeline described above.

The plugin always extends, never replaces, the core scoring path. If something is wrong in the storefront search but right in core, look in the plugin's decorators (`ProductSearchRouteDecorator`, `ElasticsearchHelperDecorator`).

## 8. Investigation cookbook

When a query produces a surprising ranking, walk this checklist before diving into source code.

### 8.1 What does the analyzer actually emit?

```bash
# Search-side tokens for the user's query
curl -s -X POST "${OPENSEARCH_URL}/${INDEX}/_analyze" -H 'content-type: application/json' -d '{
    "analyzer": "sw_german_technical_term_search_analyzer",
    "text": "5,5"
}' | jq '.tokens[].token'
```

Repeat with `sw_whitespace_analyzer` for the `.exact` subfield.

### 8.2 What's actually in the index for the suspect document?

```bash
curl -s "${OPENSEARCH_URL}/${INDEX}/_termvectors/${DOC_ID}?fields=productNumber.search" | jq '.term_vectors'
```

Often this surfaces the answer immediately — e.g. a UUID was decomposed into many tiny number tokens, one of which collides with the user's query.

### 8.3 What does ES say about its own scoring?

```bash
curl -s -X POST "${OPENSEARCH_URL}/${INDEX}/_search?explain=true" -H 'content-type: application/json' -d '{ "query": { … the query the builder produced … } }' | jq '.hits.hits[]._explanation'
```

The explanation tree tells you which dis_max clause won, what TF/IDF/length-norm contributed, and what the per-field boost did.

### 8.4 What does the merchant's search config say?

```sql
SELECT field, ranking, tokenize, and_logic, use_exact_subfield, searchable
  FROM product_search_config_field
  JOIN product_search_config ON product_search_config_field.product_search_config_id = product_search_config.id
 WHERE product_search_config.language_id = ?
 ORDER BY ranking DESC;
```

A ranking of 1000 on `productNumber` vs 700 on `name` is a 1.43× signal multiplier between the two fields. A `use_exact_subfield = 0` on `name` means the high-boost lane is off for that field.

### 8.5 Worked examples

| Symptom | Root cause | Fix |
|---|---|---|
| `5,5` matches products whose `productNumber` is `BC-031668-55` | `catenate_all` produces `55` from `5,5` on the search side, productNumber's `55` token matches | Enable `useExactSubfield` on `productNumber` (this stack ships that migration) |
| `5,5` matches a product whose number is a UUID like `SW_019daa…c55a` | `split_on_numerics` shreds UUID hex into many short tokens including `55` | Same fix as above. The `.exact` subfield is a single keyword, the UUID can't shadow `5,5` literal hits |
| `LaserJet` query scores higher than `laserjet` query against the same doc | Case-split runs before lowercase, so the casing-rich form makes a 3-clause bool/should | Acceptable today; documented in §3.3. Disable `split_on_case_change` if it causes pain |
| Search returns nothing for two-character queries (`BC`) | `sw_length_min: 2` filters tokens shorter than 2 from the technical-term chain | Use ngram subfield (`min_gram=3` default) for prefix; or lower `sw_length_min` if 2-char queries are common |

## 9. Where to look in the source

| Area | File |
|---|---|
| Field types and `buildTextFieldConfig` | `src/Elasticsearch/Framework/AbstractElasticsearchDefinition.php` |
| Per-field mapping for products | `src/Elasticsearch/Product/ElasticsearchProductDefinition.php` |
| Tokenization on the ES path | `src/Elasticsearch/Framework/DataAbstractionLayer/ElasticsearchTokenizer.php` |
| Per-token, per-field clause assembly | `src/Elasticsearch/FieldQueryBuilder.php`, `TranslatedFieldQueryBuilder.php`, `NestedFieldQueryBuilder.php` |
| Token-level orchestration | `src/Elasticsearch/TokenQueryBuilder.php` |
| Storefront entry point | `src/Elasticsearch/Product/ProductSearchQueryBuilder.php` |
| Analyzer YAML | `src/Elasticsearch/Resources/config/packages/elasticsearch.yaml` |
| BM25 and minScore | same YAML, plus `src/Elasticsearch/Framework/ElasticsearchHelper.php` for the cutoff |
| Integration spec | `tests/integration/Elasticsearch/Product/SearchCasesTest.php` |
| Plugin layer | `custom/plugins/SwagCommercial/src/AdvancedSearch/Domain/Search/` and `…/Indexing/` |

## 10. Conventions for adding new search fields

1. Decide which subfields you need: `.exact`? technical decomposition? length norm?
2. Add the mapping in the relevant `…ElasticsearchDefinition::getMapping()` using `buildTextFieldConfig(…)`.
3. If the field needs a merchant-tunable rank, add it to `product_search_config_field` (with a migration) and seed sensible defaults.
4. Add at least one scenario in `tests/integration/Elasticsearch/Product/SearchCasesTest.php` exercising the field with a representative term.
5. Update §2's decision table here so future contributors see the precedent.
