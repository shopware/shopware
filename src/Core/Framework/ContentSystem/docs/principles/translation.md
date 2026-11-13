# Translation

Translation lets an element property hold one value per language in a stored layout, and the module serves one of those values per request.

## Translations live inline in the element tree as language maps

A property whose element type declares `translatable: true` stores a non-empty map from language id to a value of the declared primitive. The property never stores a bare value, an empty map or a null. Layout structure, the layout name and the other stored parts of an element are not translated. Every key is a language id in lowercase UUID hex, and the DAL write, `update-element-properties` and `translate-element` reject any other key. The DAL write stores a key that names no `language` row, and diagnostics report it only as a warning. Inline mapping tokens may sit in every entry, under the [mapping identity rule](data-mapping.md#a-mapping-is-identified-by-its-typed-source-not-by-a-dotted-key).

Why: A translation table keyed by element id needs reconciling on every structural edit. A malformed key matches no language chain, so its entry would be stored and never served. A key for a deleted language never reaches serving, because reduction selects only along the request's chain.

Decided against: A translation table or per-language layout variants.

In code:

- `ContentLayoutDefinition` has one `StoredElementListField` and no translation association.
- `ContentLayoutDefinition` keeps `name` a plain `StringField`.
- `PropertyType::admits()` admits only a non-empty map whose every entry matches the declared primitive.
- `PropertyType::nonLanguageKeys()` owns the rule that a key is a language id in lowercase UUID hex.
- `PropertyTypeConformanceValidator` rejects each such key on the DAL write.
- `AbstractLayoutMutation` rejects the first one in `UpdateElementProperties` and `TranslateElement`.
- `ViolationCode::DanglingLanguage` is the warning for a key that names no language.
- See [Layout/Element/README.md](../../Layout/Element/README.md#translatable-properties).

## `translatable` is valid only on a lone primitive type

A top-level property declared as a lone `string`, `integer`, `number` or `boolean` may carry `translatable`. Any union, `type: [string]` included, is a declaration error. The DAL write stores a map under an undeclared key verbatim, while `update-element-properties` and `translate-element` reject that key. The module never reads a map under an unflagged key as a translation.

Why: The module checks every entry of a language map against one declared primitive, and a union names no single primitive to check against.

In code:

- `TranslatableTypeValidator` rejects the flag on any declaration but a lone primitive.
- `StoredTreePreparer` reduces flagged keys only.
- See [custom-types.md](../../Layout/Type/docs/custom-types.md).

## A value the module produces is declared bare and wrapped by one owner

A client authors the language map on every path, and the module never wraps a value a client sent. A type default, a binding input default and a preset value are declared bare. `PropertyType::inStoredShape()` alone wraps these values under `Defaults::LANGUAGE_SYSTEM`.

Why: Bringing a client value into shape is the client's task, and no translation-unaware client exists. Producing a default in the stored shape is seeding, not a rewrite of a client value, and it has [one owner](values.md#a-rule-about-a-stored-value-has-one-owner-and-every-path-that-needs-the-rule-calls-that-owner).

In code:

- `PropertyType::inStoredShape()` wraps a non-null value for a translatable type.
- See [applying.md](../../Binding/docs/applying.md).

## A required translatable property is filled only by its system-language entry

An empty string, `false` or `0` in that entry fills it. An optional translatable property may lack that entry and still serve its other languages.

Why: Every language chain the platform builds for a request ends at the system language. A required property filled only by another entry would render absent on every request whose chain skips that entry.

In code:

- `LayoutDiagnostics` credits a required translatable property through its system-language entry only.
- See [violation-rules.md](../../Diagnostics/docs/violation-rules.md).

## Language reduction happens on the server, and nothing downstream receives a language map

In FULL mode, `StoredTreePreparer` replaces each language map with its chain entry, the first entry along the language chain of the sales-channel context. Reduction is the first step of the [fixed preparation order](context-wiring.md#preparation-and-resolution-run-in-one-fixed-order). The chain is the selected language, at most one parent, then the system language. Each property key selects its own chain entry. A map with no chain entry serves as an absent property. No data loader, template, Store API response or client receives a language map, and the value each one receives derives from the chain entry. A translatable `string` property may be a loader `PropertyReference` input, so each language entry can select what the loader loads. The Admin API keeps the full maps.

Why: Language maps shipped to consumers would enlarge responses and leak other languages to the storefront. The DAL takes a translated JSON field other than custom fields whole from the first language that holds one. A partial translation there loses the base text of every key it skipped. A map without a chain entry is ordinary authoring for a language the channel does not serve, so a throw would fail a valid page.

Exceptions: A preparation listener receives the full maps, as [rendering.md](rendering.md#the-two-tree-replacement-events-fire-at-fixed-points) states. An element of an unregistered component, and a key no type declares translatable, keeps its values unreduced.

In code:

- `StoredTreePreparer` selects the chain entry along `SalesChannelContext::getLanguageIdChain()`.
- It throws `TRANSLATION_SHAPE_INVALID` on a non-map value and on a chain entry of another primitive, an [internal fault](failure-and-loss.md#the-cause-of-a-defect-sets-its-classification-and-http-status-is-a-separate-axis).
- `ConfigKeySpecification::admitsDeclaredPrimitive()` admits a lone `string` declaration, translatable or not, as a `string` reference.
- `ContentRouteTranslationTest::testAMapWithNoChainEntryServesTheKeyAsUnset` pins that the served properties carry no key for a map with no chain entry.
- See [pipeline-steps.md](../pipeline-steps.md).
