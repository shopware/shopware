# Content System Data Mapping

A field mapping binds one declared `mappable: true` element property to a typed source reference. The same registered providers supply the Administration candidate catalogue, the server-side write validator, and the runtime resolver. A client cannot create a mapping by inventing a source or member path.

## Source providers

Register an `AbstractMappingCandidateProvider` service with the `content_system.mapping_candidate_provider` tag. `supports($rootSource)` and `provide($rootSource)` describe the candidates available for a layout root source. Return only values safe to expose to layout authors, with explicit effective value type, cardinality, label, description, and optional projection. Do not derive member lists by reflecting over entities, loader results, requests, or contexts.

Root candidates use `MappingSourceReference::root($contextKey, $path)`. Non-root providers may use another stable source type and id, an optional validated config, and an optional member path. Candidate identity and runtime identity must match exactly. When providers overlap, the first provider in service-tag priority order owns that candidate and resolves it at runtime.

For non-root sources, override `supportsSource()` and `resolveSource()`. Runtime dispatch first finds the provider that offered the exact candidate for the layout's root source, then checks that provider's `supportsSource()` before calling `resolveSource()`; a broad `supportsSource()` match alone does not claim another provider's candidate. The runtime context contains the current element, its already-resolved loader values keyed by requirement key, root values, the Storefront context and request when available, and the rendering cache context. Use only the values declared by the provider's catalogue. When deriving cacheable external data, add its cache tags to the supplied cache context. Return `null` when the source is unavailable or has no value; rendering omits the mapped property and continues.

A loader-backed provider can offer a source whose stable config identifies a loader or requirement. It must validate that config at the mutation/write boundary, declare each exposed result member in its candidates, and resolve from a validated loader result. It must not trust a client-supplied loader name or path, invoke arbitrary loaders, or expose unlisted result members. Plugin providers are supported; app-based provider registration is not part of the current contract.

## Built-in Storefront context source

The framework provider offers only these public string values:

- `context:storefront.currency.isoCode`
- `context:storefront.currency.symbol`
- `context:storefront.language.localeCode`
- `context:storefront.tax.state`

The full `SalesChannelContext`, customer data, and request attributes are not candidates. Extend this list only with an explicit review of value sensitivity and cache behavior.

## Stored mappings and rendering

A property's optional `defaultMapping` declaration names a typed source reference. On insertion, the mutation pipeline seeds it only if the layout's root source offers a compatible candidate; other layouts leave it unset. Existing wiring wins, and clearing the seeded mapping exposes the property's authored/resolved-by control so the author can choose a specific entity. A mapping is stored as a root-scoped context consumer keyed by its destination property. The consumer carries a `source` object (`type`, `id`, optional `config`, optional `path`) and an optional catalogued projection. Root references resolve against layout ambient data; other typed references are routed to their registered provider. Missing provider values omit the mapped property. They do not produce a `null` value or fall back to the authored property value.

Inline text mapping remains a separate root-member feature. Its `{{map:...}}` tokens use the same root candidate catalogue, but do not select entity or context values.
