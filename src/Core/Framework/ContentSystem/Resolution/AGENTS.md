@README.md

## Source Code References

- `ElementResolver` - per-element resolution kernel (DI service); `resolve(ContentElement|string $element, ResolutionContext $context): list<PropertyResolution>` — a `string` `$element` is taken directly as the component-type registry key (no `getComponent()` call), so a caller can resolve by type name without a `ContentElement`. For each declared type property: produces a `PropertyResolution` (Primitive carries static value; Reference collects parent + loader candidates and applies deterministic conservative default selection via `pickDefault`)
- `AvailableContextResolver` - context-walk kernel (public Core DI service); `resolve(string $targetElementId, array $tree, array $rootContext): list<ProvidedContext>` — walks the element tree to compute which context is available at a target position (ancestor providers + root-ambient context for top-level elements); section-agnostic: root-ambient set is passed in by the caller
- `PropertyResolution` (`@internal final readonly`) - resolution of one declared element-type property at a position; fields: `key (string)`, `kind (PropertyKind)`, `required (bool)`, `type (?string)`, `default (mixed)`, `fqcn (?string)`, `resolved (?ResolutionCandidate)`, `candidates (list<ResolutionCandidate>)`. `type` and `fqcn` are mutually exclusive by `kind`: `Primitive` sets `type` (the primitive type string) and leaves `fqcn` null; `Reference` sets `fqcn` (the class-string) and leaves `type` null
- `ResolutionCandidate` (`@internal final readonly`) - one possible source for filling a Reference property; fields: `via (CandidateVia)`, `contextKey (?string)`, `providerElementId (?string)`, `path (?string)`, `distribution (?DistributionStrategy)`, `contextType (?ContextType)`, `loaderSource (?string)`, `configTemplate (?array<string, mixed>)`, `configComplete (bool)`
- `ProvidedContext` (`@internal final readonly`) - a single context value available at a position: `contextKey (string)`, `fqcn (string)`, `contextType (ContextType)`, `providerElementId (?string)`, `distribution (DistributionStrategy)`, `path (?string)`
- `ResolutionContext` (`@internal final readonly`) - input bundle for `ElementResolver::resolve()`; fields: `elementId (string)`, `available (list<ProvidedContext>)`
- `PropertyKind` (`@internal` enum:string) - `Primitive` / `Reference`
- `CandidateVia` (`@internal` enum:string) - `Parent` / `Loader`

## Constraints

- `ElementResolver::pickDefault()` is conservative: returns a candidate only when exactly one parent candidate exists, or (absent parents) exactly one config-complete loader candidate; otherwise returns `null` — the diagnostics layer maps `null` for required properties to `ambiguous_required`/`unresolved_required`
- `ResolutionCandidate::configComplete` (loader candidates only) is `true` only when the capability has no `requiredConfigKeys` **and** the loader's config serializer decodes the capability's `configTemplate` without a client-defect `ContentSystemException`; a client-defect decode yields `false`, any other exception propagates (`ElementResolver::isConfigComplete()`)
- `AvailableContextResolver` is section-agnostic: entity assignment yields page entity as root context; header/footer yield an empty `$rootContext`; the caller decides what to pass
- `AvailableContextResolver` is registered as a public DI service; `ElementResolver` is a private DI service. Both are consumed by `Diagnostics/LayoutDiagnostics` via constructor injection
- `ResolutionContext` is constructed per-element by the caller (e.g., `LayoutDiagnostics`) after `AvailableContextResolver::resolve()` returns the available context for that element
- `PropertyResolution::candidates` contains both parent candidates and loader candidates (parents first, loaders appended); `resolved` is the `pickDefault` selection, which may differ from the persisted assignment
- `AvailableContextResolver::resolve()` returns an empty list in two cases: `$targetElementId` is not found anywhere in `$tree`, **or** the element is found but no context is available at its position — a top-level element whose `$rootContext` is empty (e.g. header/footer), or a nested element whose ancestors expose no provider. An empty result therefore does not by itself distinguish "element not in tree" from "no context available"
- `ElementResolver::resolve()` returns an empty list (not a list of unresolvable `PropertyResolution`s) when the element's component type is not registered in the type registry — no resolution is attempted for an unknown type
