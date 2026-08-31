# Naming

How components in this module are named. This is the reasoning a contributor uses to name a new class, not a catalog of the existing ones: you should come away able to name something this module has never seen. The general principles are here; the two subjects that need room of their own are linked below.

## A name answers two questions

Every class name resolves "about what?" and "what kind of thing?". `ContentLayoutDefinition` concerns the persisted content layout (subject) and is a DAL definition (role). Get both right and the name places itself.

## The subject is what the class is about, not what it holds

A class is named for the thing it operates on, never for its dependencies. `LayoutWriteBoundary` admits a layout write; the seeder, style normalizer and reconciler it is injected with are only how it does so. When the subject is unclear, ask which concept a reader would expect under that name, not which services the constructor lists.

The module separates a few subjects on purpose: the persisted entity, the in-memory element model an operation works on, an admin API action, and standalone value objects that are none of these. So a write-time guard on the entity and an in-memory predicate over the tree read differently by design, even when they cooperate on one feature.

## Subjects and roles with their own page

- [Stored and rendered element models](docs/stored-and-rendered.md) — which of the two element models a class is about, and how a prefix on the subject says so.
- [Role suffixes](docs/role-suffixes.md) — what each role suffix promises, and what breaks the promise.

## Lean on the module's domain words

The module has a working vocabulary, and names borrow from it rather than inventing parallels. "Context" means data passed between elements as they render, not the framework's request `Context`. "Specification" means a declared schema or contract. "Root source" is the registered origin of a layout's root-ambient context — an entity type, a section, or "none" — one domain word for that single role, so a new name reaches for it rather than coining "owner". "Binding" is not available as that parallel either: it already names the relationship between a reference property and the source that fills it, the sense `ViolationScope::Binding` carries, and a `BindingSpecification` is an authored declaration of such a binding. Coining a synonym splits the vocabulary and forces everyone to learn both.

"Stored" and "Rendered" are domain words on the same footing. Do not coin "persisted", "raw", "authored", "output" or "resolved" as a parallel: a second word for one of the two models is worse than no word, because a reader then has to learn which pairs with which.

## Families vary one word and hold the rest still

When several types differ along a single axis, that axis is the only thing that moves in their names. The distribution configs name themselves by strategy and share the rest, so they read as one set rather than as unrelated classes. Adding the next member of a family changes the discriminator and nothing else.

The two-model prefixes are a family of exactly this shape, which is why `StoredElement` and `RenderedElement` differ in one word. Where a concept exists on both sides, name it so the two differ only in the prefix; where it exists on one side only, the missing counterpart is itself information and no placeholder is invented for it.

## Names are layered; take the nearest layer

Three conventions stack. The module inherits Shopware-wide idioms: a DAL entity is a definition with an entity and a collection, an event listener is a subscriber, a constraint pairs with its validator. On top sit the module's own domain families, the two-model prefixes among them. On top of those sits the behavioral-suffix system used by the validation and resolution subsystem. A new class adopts the convention of the layer it belongs to; do not invent a style for a class the framework already has a word for.

## Add a new suffix only to mark a real distinction

A novel suffix is justified when it encodes a behavioral difference the generic word blurs, and the justification travels with it. The subsystem separates the service that owns a decision from the one that applies it and surfaces the resulting violations, because collapsing both under one word hides which is which. The same test admitted `Planner` next to `Distributor` and `Lowering` next to `Codec`: in each pair the generic word would have hidden whether the class decides or acts, and whether the translation can be undone. If an existing suffix already carries the meaning, use it.

## No exceptions

Every class in this module must follow these principles, with no grandfathered outliers. A name that conflicts with a principle here is a defect to correct, not a precedent to copy: the name is what changes, not the convention. An element subject with no `Stored`/`Rendered` prefix is that kind of defect. So is `Layout/Scaffolding/`, which uses "scaffolding" for a service performing a temporary structural modification — an older sense predating the two-model split, and so a name to correct rather than a second meaning to keep alongside the [`*Scaffolding`](docs/role-suffixes.md) suffix.
