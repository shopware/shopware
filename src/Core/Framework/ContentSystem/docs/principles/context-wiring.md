# Context wiring

Context wiring brings a value to an element from a parent's [provider](README.md#glossary) or from the layout's [root context](README.md#glossary).

## Context flows only between adjacent elements

An element reaches root context directly. A provider serves its direct children only. An ancestor passes a value on only by consuming and redistributing it. Any element reaches root context through its own root-scoped [consumer](README.md#glossary), never hop by hop. A root-scoped consumer therefore may not redistribute. A root candidate outranks a parent candidate for the same key. When the candidates for a key are ambiguous, the module selects none of them.

Why: A relay chain makes tree shape decide delivery. An arbitrary pick among ambiguous candidates can deliver a wrong value silently.

Exceptions: An event listener may write a property on any element. The adjacency rule applies to wiring only.

In code:

- `ContextDistributor` serves direct children.
- `ContextDeliveryResolver` fills `ConsumerScope::Root` consumers.
- `ElementResolver` ranks a root candidate first.
- `ContextDeliveryResolverTest` pins the direct-children and root-consumer behaviors.
- See [consumers.md](../../Layout/Element/Context/docs/consumers.md).

## Preparation and resolution run in one fixed order

Placeholder substitution runs first, then wiring validation and [redistribute derivation](../../Layout/Element/Context/docs/redistribution.md). Data resolution over the whole [forest](README.md#glossary) runs next, then the page-level data that forms the root context, then context delivery. A loader therefore never receives delivered context or the page entity. The preparation event fires before this order starts, as [rendering.md](rendering.md#the-two-tree-replacement-events-fire-at-fixed-points) states.

Why: A provider may hand a loaded value to a child, so delivery before data resolution would find that value not yet loaded. Placeholder substitution never descends into a map.

In code:

- `ElementLowering::lower()` loads data before it delivers context.
- `StoredTreePreparerTest` pins this order.
- See [pipeline-steps.md](../pipeline-steps.md).

## The write gate computes each delivery rule it checks exactly as serving computes it

The [write gate](README.md#terms-that-are-easy-to-confuse) checks these delivery rules: a provider's child-facing key, consumer key matching and the provider collision rule. For each of these rules, the write gate computes the same result as serving. A divergence between the write gate and serving on one of these rules is a defect to fix, not a note.

Why: A stricter gate rejects servable layouts. A looser gate accepts layouts that fail when served.

Exceptions: The write gate does not guarantee a value for an optional reference or for a reference picked from a loader candidate. Resolvability inputs may change after the write. By design, the module does not check that change.

In code:

- `WiringPlanner::plan()` and `AvailableContextResolver` share `ProviderDeliveryKeyResolver`.
- `LayoutDiagnostics` matches consumer keys with `ContextPathResolver::matches()`.
- `AvailableContextResolverTest` pins the shared key resolver.
- See [Validation/README.md](../../Validation/README.md).

## Also true by construction

- Each finished rendered key gets exactly one `ValueOrigin`. A key that a finalization listener added gets `ValueOrigin::Injected` instead of throwing or dropping. Each property-writing loop in `RenderedElementFactory` records the origin of its value. A contested key is therefore recorded for the member that won it. Reordering those loops can recategorise index entries while every value stays correct. The [decomposed and data formats](README.md#glossary) carry every key of the full format: [ValueOrigin.php](../../Output/Index/ValueOrigin.php)
