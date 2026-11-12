# Data mapping

Mapping lets an author use approved live values while retaining the layout's authored text and manual selections.
The runtime, API, and provider contracts are in [Mapping/README.md](../../Mapping/README.md).

## The catalogue is an explicit exposure contract

Providers curate typed candidates; the module never derives exposed paths by reflecting over entities, loader
results, requests, or contexts. A source is admitted only when its complete typed identity and configuration
match a candidate offered for the layout's root source. Effective value type and cardinality must fit the
destination. Provider output is checked against its advertised type at render time too.

Why: Scalar values extracted from entities cannot rely on the entity encoder's field protection. A candidate
therefore vouches for a value that is safe to expose. A source withdrawn after a layout was saved must not remain
readable through that layout.

Decided against: Arbitrary path traversal, automatically exposing entity fields, and built-in Storefront-context
sources. Context-dependent values use loaders. Plugin providers use explicit bundle-owned source types and local
IDs; names are not automatically rewritten.

In code:

- `StoredMappingInspector` owns admission for mutations, defaults, diagnostics, and persisted writes.
- `MappingTypeCompatibility` and `MappingRuntimeResolver` apply type admission and runtime checks.
- `ContentSystemMappingCandidateRegistryTest` pins exact identity, configuration equality, and owning-provider dispatch.
- See [source providers](../../Mapping/docs/source-providers.md).

## A mapping is identified by its typed source, not by a dotted key

A whole-field mapping is an optional, root-scoped, unaliased, non-redistributing consumer carrying a typed `source`.
Its consumer-map key names the destination property. Ordinary wiring has no source. Inline mappings are authored
tokens in opted-in top-level string properties, not stored consumers. `mappable` and `inlineMappable` are mutually
exclusive. `defaultMapping` declares initial source wiring, not a second property type or a mandatory consumer.

Why: Ordinary root wiring and whole-field mappings share a container but have different admission and missing-value
rules. Using path punctuation to distinguish them would misclassify whole-source and plugin references.

In code:

- `MappingConsumers` is the shared discriminator.
- `StoredElementWiringDecoder` and `StoredTreeWiringConstraints` enforce the source-bearing shape.
- `InlineMappableTypeValidatorTest` and `MappingConsumersTest` pin declaration exclusivity and classification.
- See [type declarations](../../Layout/Type/docs/custom-types.md#mappable).

## Initialization preserves authored choices

Default mappings and consumer mirroring initialize only explicit `freshlyScaffolded()` IDs: new elements, presets,
replacement nodes, and wrapping containers. Copies and ordinary reattachments preserve selected entities, existing
mappings, and deliberate unmapping. Saving through DAL, Admin CRUD, or Sync does not invent mapping consumers.

Why: A new ID does not mean new authored content. Reinitializing a copied element would silently override the author's
manual selection. Mapping defaults therefore differ from stored property defaults seeded at the DAL write boundary.

In code:

- `DefaultMappingSeeder`, `MutationPipeline`, and `PersistedLayoutMutator` use the explicit scaffold report.
- `AttachElement` and `AttachElements` distinguish preset insertion from authored attachment.
- `DefaultMappingCreationTest` and `MutationPipelineTest` cover initialization and preservation.
- See [mutation operations](../../Mutation/docs/operations.md).

## Admission and missing data are separate outcomes

Whole-field missing or empty source values permit authored/loader fallback. A resolved null is delivered and
overrides those values. A rejected or incompatible mapping stays omitted, including from child providers and keyed
distribution. Inline missing or non-text values remove only the token; an empty string substitutes empty text and
surrounding prose remains. Provider failures propagate rather than becoming missing data.

Why: Ordinary missing data should not crash a page, while exposing a withdrawn source or hiding a provider fault
would violate the source contract. Static diagnostics checks capability, not a particular preview entity's value.
Mapped references select a root-scoped source; required primitive validation still needs authored values.

In code:

- `MappingRuntimeResolver` separates rejection, `noValue()`, and resolved values.
- `ContextDeliveryResolver`, `RenderedElementFactory`, and `InlineMappingInterpolator` own their output rules.
- `MappingProviderExtensionTest` and `MappingRuntimeResolverTest` pin rejection, fallback, null, and error behavior.
- See [runtime outcomes](../../Mapping/README.md#stored-mappings-and-rendering), [diagnostics](../../Diagnostics/README.md#mapping-checks), and [request error boundaries](../../Mapping/README.md#runtime-error-handling).

## Only authored token spans are expanded

The server parser captures token spans after preparation listeners and before placeholder substitution. It carries
those spans through loading; syntax inserted by placeholder or provider values never becomes new mapping intent.
After forest/page loading, inline expansion runs before context delivery and keyed selection. Original stored text
remains editable. Valid tokens in HTML markup stay literal. Interpolation escapes values; rich-text rendering still
uses the configured sanitizer and may interpret allowed markup after entity decoding.

Why: Reparsing substituted values would let external data introduce mappings. Expanding after distribution would
give parents and children different values. The editor consumes server spans instead of implementing another grammar.

In code:

- `InlineMappingTokenParser` owns token syntax; `InlineMappingMetadata` carries authored spans.
- `StoredTreePreparerTest`, `InlineMappingTokenParserTest`, and `ElementLoweringTest` pin capture and stage order.
- See [pipeline steps](../pipeline-steps.md) and [rich-text rendering](../../Mapping/README.md#rich-text-rendering).

## Resource limits and cache dependencies apply throughout mapping

The 256 KiB authored-input cap, 1 MiB expanded-property limit, and 4 MiB render-output limit bound different work.
One expansion budget includes literal text and escaped replacements across the entire forest. Output exhaustion
aborts rendering; it is not a successful partial result or an `invalid_mapping` finding. Parser-engine faults also
propagate. All mappings use the caller's cache context, including uncached renders and rejected attempts.

Why: Output limits do not bound parsing, and a fallback cache context would lose dependencies. Metadata changes must
invalidate pages using the affected mapping feature without invalidating unrelated layouts. Custom-field metadata
therefore uses a shared dependency only for renders attempting custom-field mappings; ordinary core mappings do not.

In code:

- `InlineMappingExpansionBudgetTest` and `ContentPipelineTest` pin the shared limits and failure behavior.
- `ElementLoweringTest` pins the caller-owned context; custom-field cache integration tests pin invalidation scope.
- See [mapping limits](../../Mapping/README.md#stored-mappings-and-rendering) and [cache invalidation](../../Cache/README.md#invalidation).

## Intentional overrides cannot skip source admission

The public `AbstractContentSystemMappingCandidateRegistry` is the supported decoration target. Its final
`resolveSource()` validates exact catalogue identity/configuration and collects metadata dependencies before the
protected `resolveAdmittedSource()` hook. A catalogue-only decorator inherits forwarding. Providers and decorators
remain responsible for the safety of the values they return; admission cannot prove a returned value's provenance.

Why: Overrides are an intentional extension requirement. Direct callers and decorators must receive the same
admission guarantees, including when an override returns without delegating to the core registry.

Decided against: Provider-order overrides, silent first-wins collisions, and withdrawing the supported registry
extension point. Stable duplicate declarations fail compilation; runtime collisions exclude every owner of that
name and log a warning while keeping unrelated candidates available. Other provider faults still propagate.

In code:

- `AbstractContentSystemMappingCandidateRegistryTest` pins the final guard and automatic metadata dependencies.
- `MappingProviderExtensionTest` verifies production decoration and whole-field/inline overrides.
- See [plugin sources and overrides](../../Mapping/docs/plugin-sources.md).
