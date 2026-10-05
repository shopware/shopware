# Resolution

Property-resolution kernel for the ContentSystem. Given an element's position in a layout tree, determines how each declared type property can be filled. Primitives carry a static value. Reference properties collect candidate sources (ancestor providers, the layout's root-ambient context, and data loaders) with a deterministic conservative default selection.

## Key Classes

Two kernels do the work. `ElementResolver` resolves one element's declared properties at a position. `AvailableContextResolver` computes which context reaches that position. One formula serves every depth: what the ancestor path exposes, simulating runtime redistribution, plus the layout's root-ambient set appended verbatim.

## Candidate selection

`pickDefault()` picks over ordered pools. Exactly one `Root` candidate wins; several return `null` with no fallback to `Parent`. Without `Root`, exactly one `Parent` candidate wins; several return `null`. Without either, exactly one config-complete loader candidate wins. Otherwise `null`, which diagnostics maps to `ambiguous_required` or `unresolved_required` for required properties.

`ResolutionCandidate::configComplete` (loader candidates only) is `true` when the completion residue is empty (`ContentSystemDataLoaderMap::residualConfigKeysFor()`: the loader's required `LoaderConfigSpecification` keys minus the `configTemplate` keys) and the loader's config serializer decodes `configTemplate` without a client-defect `ContentSystemException`. A client-defect decode yields `false`; any other exception propagates (`ElementResolver::isConfigComplete()`).

`ElementResolver::storedCandidate()` is applied wiring, not a candidate. A `Stored` requirement whose produced type resolves and is assignable to the declared FQCN becomes `resolved` directly (`$stored ?? pickDefault(...)`), never a `candidates` entry. A config that fails to resolve (client defect) or resolves to a mismatched type yields no `Stored` candidate; [Diagnostics](../Diagnostics/README.md) reports them as `InvalidConfig` and `MismatchedReferenceType`. A `string`-only `resolve()` call never produces one: only a `StoredElement` carries `dataRequirements`.

`PropertyResolution::candidates` holds context candidates (`Parent`, `Root`, in availability order), then loader candidates. `resolved` is the `pickDefault` selection and may differ from the persisted assignment. `ElementResolver::resolve()` returns an empty list for a component type missing from the type registry.

## Available context

- `AvailableContextResolver` is section-agnostic: entity assignment yields the page entity as root context; header and footer yield an empty `$rootContext`; the caller passes it.
- `resolve()` returns an empty list when `$targetElementId` is not in `$tree`. So does a found element with empty `$rootContext` whose ancestors expose no provider and re-broadcast no inflowing redistribute key. An empty result does not tell the cases apart.
- `resolve()` mirrors runtime redistribution. Walking the ancestor path top-down from an empty incoming set, each ancestor exposes to the next position only, with `root: false`:
  - (a) its declared providers whose own property resolves on it, backed via the shared `ElementResolver` against the chain incoming plus only the root-ambient entries its own `ConsumerScope::Root` consumers write to that provider's key. The written key `propertyAlias ?? consumerKey` is compared verbatim against the provider key; the ambient key matches the consumer key by `ContextPathResolver::matches()`. A consumer-less ancestor's provider is not backed by a type-matching ambient entry. `Rendering/ContextDeliveryResolver::overlayRootContext()` writes the key verbatim, so a dotted consumer key without `propertyAlias` writes the full dotted key, which no provider key reads. With a `propertyAlias` equal to the provider key it backs the provider.
  - (b) the keys it re-broadcasts via `redistribute: true` consumers whose key is in its own chain incoming context, re-exposed under `consumerAlias` when set.
- `resolve()` omits ancestor providers whose declared property type is primitive (`isPrimitive()`): only Reference (FQCN-typed) slots become `ProvidedContext` entries, although `getProvidesContext()` yields the primitive slot.
