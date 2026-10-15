# Content System Data Mapping

A field mapping binds one declared `mappable: true` element property to a typed source reference. The same registered providers supply the Administration candidate catalogue, the server-side write validator, and the runtime resolver. `StoredMappingInspector` owns property opt-in, source identity, type, and cardinality admission for map mutations, default seeding, and persisted writes. A client cannot create a mapping by inventing a source or member path.

## Source providers

Register an `AbstractMappingCandidateProvider` service with the `content_system.mapping_candidate_provider` tag. `supports($rootSource)` and `provide($rootSource)` describe the candidates available for a layout root source. Declare stable display names in `registeredCandidateKeys()` so the container build can reject collisions before requests use the catalogue. Providers whose candidates come from persisted/runtime data return an empty declaration and must handle collisions in that dynamic data themselves. Return only values safe to expose to layout authors, with explicit effective value type, cardinality, label, and description. Do not derive member lists by reflecting over entities, loader results, requests, or contexts.

Providers may attach internal metadata cache tags to a candidate. The registry adds those tags to the rendering cache context only when that candidate is consulted, so a core-only mapping does not inherit custom-field invalidation. The Admin candidate catalogue has its own global `custom_field` HTTP cache tag because it returns the full dynamic catalogue; writes to custom fields, sets, and set relations invalidate that catalogue cache and the relevant rendered field/set tags. Candidate cache tags are not part of the API schema.

Root candidates use `MappingSourceReference::root($contextKey, $path)`. Non-root providers may use another stable source type and id, an optional validated config, and an optional member path. Candidate identity and runtime identity must match exactly: `MappingCandidate::$path` must equal the source's `displayName()`, and each `type:id.path` must be unique for a root source. Duplicate candidate keys throw during catalogue construction; providers must coordinate keys rather than relying on registration order.

The registry dispatches both whole-field mappings and inline tokens to the provider that offered the exact candidate for the layout's root source. For root sources, override `resolveRootSource()` when a candidate needs a storefront-facing accessor, translation fallback, or inheritance handling; the inherited reader preserves existing `Struct` path providers. For non-root sources, override `supportsSource()` and `resolveSource()`; a broad `supportsSource()` match alone does not claim another provider's candidate. The runtime context contains the current element, its already-resolved loader values keyed by requirement key, root values, the Storefront context and request when available, and the rendering cache context. Return only values declared safe by the provider's catalogue, including scalar and array values that do not pass through entity field protection. When deriving cacheable external data, add its cache tags to the supplied cache context. Return `MappingSourceResolution::resolved($value)` for a value, including a present `null`; return `noValue()` for missing source data. The registry logs provider exceptions and returns a failed result. Whole-field rendering delivers only resolved results, and a present `null` remains present; inline rendering removes only an unavailable, failed, or non-text-convertible token while preserving surrounding authored text. Mapping token syntax from supported inline-mappable properties never reaches Storefront output.

A loader-backed provider can offer a source whose stable config identifies a loader or requirement. It must validate that config at the mutation/write boundary, declare each exposed result member in its candidates, and resolve from a validated loader result. It must not trust a client-supplied loader name or path, invoke arbitrary loaders, or expose unlisted result members. Plugin providers are supported; app-based provider registration is not part of the current contract.

The product provider returns `MediaEntity` for `product.cover` and a position-sorted `MediaCollection` for `product.media` directly. Providers reshape intermediate data before returning it; the candidate's `valueType` describes that delivered value.

## Built-in Storefront context source

The framework provider offers only these public string values:

- `context:storefront.currency.isoCode`
- `context:storefront.currency.symbol`
- `context:storefront.language.localeCode`
- `context:storefront.tax.state`

The full `SalesChannelContext`, customer data, and request attributes are not candidates. Extend this list only with an explicit review of value sensitivity and cache behavior.

These values come from the `storefront_context` element data loader. A whole-field map operation stores its internal requirement alongside the consumer; an inline token causes the same requirement to run during rendering. The requirement result is private to mapping and never appears as a rendered property. Because changes to currency symbols and language locale codes cannot currently be tracked for invalidation, the loader marks a page using these values uncacheable through the normal loader result.

## Stored mappings and rendering

A property's optional `defaultMapping` declaration names a typed source reference. Fresh insertion, preset attachment, and replacement scaffolds seed it only if the layout's root source offers a compatible candidate; other layouts leave it unset. Each mutation explicitly reports freshly scaffolded ids for seeding and consumer mirroring. Existing wiring wins, and duplication preserves the copied mapping or deliberate unmapping. Clearing the seeded mapping exposes the property's authored/resolved-by control so the author can choose a specific entity. A mapping is stored as a root-scoped context consumer keyed by its destination property. The consumer carries a `source` object (`type`, `id`, optional `config`, optional `path`). Both root and other typed references resolve through their registered provider. For whole-field mappings, a `resolved(null)` value is delivered as null and overrides authored content. A missing source (`noValue()`) or an empty string or countable collection falls back to the field's authored or loader-resolved value. A provider failure or invalid mapping remains omitted and does not fall back. Inline token behavior is separate: unavailable tokens are removed while surrounding authored text remains.

Inline text mapping uses `{{map:...}}` tokens in opted-in top-level string properties. Root and typed non-root tokens use the same root-source candidate catalogue. Mapping-looking text outside those properties or in HTML attributes stays literal. Preview remains read-only; authors edit the stored text through the property controls.

Each opted-in property is limited to 256 KiB of authored text. Token scanning is linear in that bounded input size; the parser does not impose a separate token-count cap.
