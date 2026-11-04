# Resolution

Property-resolution kernel for the ContentSystem. Given an element's position in a layout tree, determines how each declared type property can be filled. Primitives carry a static value. Reference properties collect candidate sources (ancestor providers, the layout's root-ambient context, and data loaders) with a deterministic conservative default selection.

## Key Classes

Two kernels do the work. `ElementResolver` resolves one element's declared properties at a position. `AvailableContextResolver` computes which context reaches that position. One formula serves every depth: what the ancestor path exposes, simulating [runtime redistribution](../Layout/Element/Context/docs/redistribution.md), plus the layout's [root context](../docs/principles/README.md#glossary) appended verbatim.

## Candidate selection

`pickDefault()` selects one candidate for a reference property. It takes the first non-empty pool among the `Root`, `Parent` and config-complete loader candidates. A pool with one candidate wins. A pool with several returns `null` and never falls through. Diagnostics maps `null` to `ambiguous_required` or `unresolved_required` for required properties.

`ResolutionCandidate::configComplete` (loader candidates only) is `true` when the completion residue is empty (`ContentSystemDataLoaderMap::residualConfigKeysFor()`: the loader's required `LoaderConfigSpecification` keys minus the `configTemplate` keys) and the loader's config serializer decodes `configTemplate` without a client-defect `ContentSystemException`. A client-defect decode yields `false`; any other exception propagates (`ElementResolver::isConfigComplete()`).

`ElementResolver::storedCandidate()` is applied wiring, not a candidate. A `Stored` requirement whose produced type resolves and is assignable to the declared FQCN becomes `resolved` directly (`$stored ?? pickDefault(...)`), never a `candidates` entry. A config that fails to resolve (client defect) or resolves to a mismatched type yields no `Stored` candidate; [Diagnostics](../Diagnostics/README.md) reports them as `InvalidConfig` and `MismatchedReferenceType`. A `string`-only `resolve()` call never produces one: only a `StoredElement` carries `dataRequirements`.

`PropertyResolution::candidates` holds context candidates (`Parent`, `Root`, in availability order), then loader candidates. `resolved` is the `pickDefault` selection and may differ from the persisted assignment. `ElementResolver::resolve()` returns an empty list for a component type missing from the type registry.

## Available context

- `AvailableContextResolver` ignores the section. The caller passes `$rootContext`: the page entity for entity assignment, empty for header and footer.
- `resolve()` returns an empty list when `$targetElementId` is not in `$tree`. So does an empty `$rootContext` when no ancestor exposes a provider or passes on an incoming `redistribute` key. An empty result does not tell the cases apart.
- `resolve()` mirrors runtime redistribution. Walking the ancestor path top-down from an empty set, each ancestor exposes to the next position only, with `root: false`:
  - (a) its declared providers whose own property resolves on it. `ElementResolver` resolves that property against the context received from ancestors plus the root context entries that the element's `ConsumerScope::Root` consumers write to that provider's key. The written key `propertyAlias ?? consumerKey` is compared verbatim with the provider key. The root context key matches the consumer key by `ContextPathResolver::matches()`. A provider on an ancestor without consumers does not resolve from a type-matching root context entry. `Rendering/ContextDeliveryResolver::overlayRootContext()` writes the key verbatim, so a dotted consumer key without `propertyAlias` writes the full dotted key, which no provider key reads. With a `propertyAlias` equal to the provider key the provider resolves.
  - (b) the keys it passes on through `redistribute: true` consumers whose key arrives from its ancestors, exposed under `consumerAlias` when set.
- `resolve()` omits ancestor providers whose declared property type is primitive (`isPrimitive()`): only Reference (FQCN-typed) slots become `ProvidedContext` entries, although `getProvidesContext()` yields the primitive slot.
