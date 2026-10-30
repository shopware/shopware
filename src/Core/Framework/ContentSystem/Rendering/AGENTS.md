> Conceptual overview and rationale live in [README.md](README.md). This file gives the local code map and invariants.

## Source Code References

All classes are `@internal` except the public `RenderedElement` model.

- `WiringPlanner` validates stored wiring and derives redistributed providers before render.
- `ElementLowering` coordinates the render pass: consumed page requirements, element loaders, root context, inline expansion, context delivery, and full/skeleton mode.
- `ElementDataResolver` resolves per-element or separately supplied page requirements. Loader inputs come from the declared config and unwrapped stored properties; results retain loader identity and cacheability.
- `ContextDeliveryResolver` walks top-down after all loading is complete. It delivers ordinary root context and applies whole-field output rules to mapping results.
- `Mapping/MappingRuntimeResolver` shares mapping admission, provider dispatch, and runtime type checks with inline interpolation. Each renderer owns its medium's output rules.
- `ContextDistributor` delivers one parent’s context to direct children. A root-scoped consumer is never filled from a parent.
- `RenderedElementFactory` applies property precedence and whole-field mapping fallback/omission rules while unwrapping stored values; `RenderedTreeFactory` mints the forest bottom-up.
- `RenderedElement` is the immutable render model. `LoweringResult` pairs the tree with per-property provenance.

## Constraints

- Complete element and consumed page requirement loading for the whole forest, then expand authored inline text before context delivery. Providers and keyed selection read the expanded forest. The real phase order is documented in [the pipeline contract](../docs/pipeline-contract.md).
- Root-ambient context is an explicit render input, never a tree lookup. Resolve page requirements only when used by root consumers, typed whole-field mappings, or recognized inline tokens in opted-in properties.
- Ordinary root consumers skip missing/null ambient roots. With a non-null root, an optional unresolvable dotted path delivers a present `null`; a required one throws `contextPathNotResolvable`. A successfully reached terminal null is delivered even when required. See [ordinary root delivery](../Layout/Element/Context/docs/consumers.md#ordinary-root-delivery).
- Whole-field mapping outcomes and inline tokens follow [mapping runtime](../Mapping/README.md#stored-mappings-and-rendering). The [runtime error handling](../Mapping/README.md#runtime-error-handling) distinguishes pipeline failure from the outer Store API, preview, and Storefront response.
- SKELETON mode resolves no loaders or context and records no property provenance.
- Record provenance at the write that wins a property key. Value precedence and provenance precedence must stay aligned.
- Context provider, consumer, alias, and redistribution rules belong to [Layout/Element/Context/AGENTS.md](../Layout/Element/Context/AGENTS.md); this directory executes those rules.
