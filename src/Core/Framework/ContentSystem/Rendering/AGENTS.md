> Conceptual overview and rationale live in [README.md](README.md). This file gives the local code map and invariants.

## Source Code References

All classes are `@internal` except the public `RenderedElement` model.

- `WiringPlanner` validates stored wiring and derives redistributed providers before render.
- `ElementLowering` coordinates the render pass: consumed page requirements, element loaders, root context, context delivery, inline expansion, and full/skeleton mode.
- `ElementDataResolver` resolves per-element or separately supplied page requirements. Loader inputs come from the declared config and unwrapped stored properties; results retain loader identity and cacheability.
- `ContextDeliveryResolver` walks top-down after all loading is complete. It delivers ordinary root context and dispatches admitted mappings through their catalogued provider.
- `ContextDistributor` delivers one parent’s context to direct children. A root-scoped consumer is never filled from a parent.
- `RenderedElementFactory` applies property precedence and mapping omission rules while unwrapping stored values; `RenderedTreeFactory` mints the forest bottom-up.
- `RenderedElement` is the immutable render model. `LoweringResult` pairs the tree with per-property provenance.

## Constraints

- Complete element and consumed page requirement loading for the whole forest before context delivery. Providers may pass loaded values to descendants. The real phase order is documented in [the pipeline contract](../docs/pipeline-contract.md).
- Root-ambient context is an explicit render input, never a tree lookup. Resolve page requirements only when used by root consumers, typed whole-field mappings, or recognized inline tokens in opted-in properties. Derive Storefront context requirements from the current whole-field mappings and inline tokens, and do not load them when those references are removed.
- A present ordinary value, including `null`, is delivered. Missing ordinary paths are absent. Mapping resolution has its own explicit outcome contract: a resolved `null` is present; no value or unavailable mapping omits the mapped property and preserves an unresolved inline token. See [mapping runtime](../Mapping/README.md).
- SKELETON mode resolves no loaders or context and records no property provenance.
- Record provenance at the write that wins a property key. Value precedence and provenance precedence must stay aligned.
- Context provider, consumer, alias, and redistribution rules belong to [Layout/Element/Context/AGENTS.md](../Layout/Element/Context/AGENTS.md); this directory executes those rules.
