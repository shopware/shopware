# Context wiring

Context wiring brings a value to an element from a parent's provider or from the layout's root context. The model is adjacency: a provider serves its direct children, and root context reaches an element directly.

## Context flows only between adjacent elements

An element reaches root context directly. A provider serves its direct children only. An ancestor passes a value on only by consuming and redistributing it. Any element reaches root context through its own root-scoped consumer, never hop by hop. A root-scoped consumer therefore may not redistribute. A root candidate outranks a parent candidate for the same key. When the candidates for a key are ambiguous, the module selects none of them.

Why: Relay chains made each intermediate container part of a wiring that the container did not author. An arbitrary pick among ambiguous candidates fails silently.

Exceptions: An event listener may write a property on any element. The adjacency rule applies to wiring only.

In code:

- `ContextDistributor` serves direct children.
- `ContextDeliveryResolver` fills `ConsumerScope::Root` consumers.
- `ElementResolver` ranks a root candidate first.
- `ContextDeliveryResolverTest` pins the direct-children and root-consumer behaviors.
- `ElementResolverTest` pins the root-first ranking.
- See [consumers.md](../../Layout/Element/Context/docs/consumers.md).

## Preparation and resolution run in one fixed order

Placeholder substitution runs first, then wiring validation and redistribute derivation. Data resolution over the whole forest runs next, then the page-level data that forms the root context, then context delivery. A loader therefore never receives delivered context or the page entity. The preparation event fires before this order starts, as [rendering.md](rendering.md#the-two-tree-replacement-events-fire-at-fixed-points) states.

Why: A provider may hand a loaded value to a child, so data resolution over the whole forest finishes before context delivery starts. Placeholder substitution never descends into a map.

In code:

- `ContentPipeline::load()` dispatches `ContentTreePreparationEvent` before `StoredTreePreparer::prepare()` runs.
- `ElementLowering::lower()` loads data before it delivers context.
- `StoredTreePreparerTest` pins this order.
- `ElementLoweringTest` pins this order.
- See [pipeline-steps.md](../pipeline-steps.md).

## The write gate computes each delivery rule it checks exactly as serving computes it

The write gate checks these delivery rules: a provider's child-facing key, consumer key matching and the provider collision rule. For each of these rules, the write gate computes the same result as serving. A divergence between the write gate and serving on one of these rules is a defect to fix, not a note.

Why: A write gate that is stricter or looser than serving fails in the direction nobody notices.

Exceptions: The write gate does not guarantee a value for an optional reference or for a reference picked from a loader candidate. Resolvability inputs may drift after the write. By design, the module does not check that drift.

In code:

- `WiringPlanner::plan()` and `AvailableContextResolver` share `ProviderDeliveryKeyResolver`.
- `LayoutDiagnostics` matches consumer keys with `ContextPathResolver::matches()`.
- `AvailableContextResolverTest` pins the shared key resolver.
- `LayoutDiagnosticsTest` pins the consumer-key matching.
- See [Validation/README.md](../../Validation/README.md).

## Also true by construction

- Each finished rendered key gets exactly one provenance category, and a key a finalization listener added gets `ValueOrigin::Injected` instead of throwing or dropping. The decomposed and data formats therefore carry every full-format key: [ValueOrigin.php](../../Output/Index/ValueOrigin.php)
