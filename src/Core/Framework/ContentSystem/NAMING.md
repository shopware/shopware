# Naming

How components in this module are named. This is the reasoning a contributor uses to name a new class, not a catalog of the existing ones. Read it before adding a type: you should come away able to name something this module has never seen.

## A name answers two questions

Every class name resolves "about what?" and "what kind of thing?". The subject is what the class concerns; the role is what it is or does. `ContentLayoutDefinition` concerns the persisted content layout (subject) and is a DAL definition (role). Get both right and the name places itself.

## The subject is what the class is about, not what it holds

A class is named for the thing it operates on, never for its dependencies. `LayoutTreeDecoder` decodes the in-memory element tree; it is injected with the layout entity definition only to reach the stored column, so it is named for the tree, not the entity. When the subject is unclear, ask which concept a reader would expect to find under that name, not which services the constructor lists.

The module separates a few subjects on purpose: the persisted entity, the decoded in-memory tree, an admin API action, and standalone value objects that are none of these. A class about the stored row carries the entity's name; a class about the decoded tree carries the tree's. So a write-time guard on the entity and an in-memory predicate over the tree read differently by design, even when they cooperate on the same feature.

## A role suffix is a contract, not decoration

Some suffixes promise behavior, and a reader is entitled to that promise. A `Validator` here is a write-boundary subscriber that rejects an invalid write. A service that only computes and returns a report is therefore not a `Validator`, however validation-shaped it feels, because the suffix would misstate where it runs and what it does. Choose a role suffix by answering behavioral questions, not by reaching for the nearest synonym: does it reject at a boundary, does it decide a pass/fail predicate, does it apply that decision to something it must first resolve. The suffix encodes the answer so the next reader does not have to open the file.

A `Registry` is the single authority over a named set and its resolution: it owns both what the valid members are and how each one resolves, so a class that merely looks a value up without owning the set has not earned the suffix. A `Reader` reads one persisted value behind a precedence rule its callers should not have to carry, encapsulating that rule so every caller reads the value the same way; the suffix promises a single encapsulated read, not a general query service.

## Lean on the module's domain words

The module has a working vocabulary, and names borrow from it rather than inventing parallels. "Context" means data passed between elements as they render, which is not the framework's request `Context`; a value object carrying such data is named with that domain sense in mind. "Specification" means a declared schema or contract. "Root source" is such a word too: the registered origin of a layout's root-ambient context — an entity type, a section, or "none". It is one domain word for that single role, so a new name reaches for it rather than coining a parallel like "owner" or "binding" for the same idea. Reusing the established word keeps a name legible to anyone who already knows the domain; coining a synonym splits the vocabulary and forces everyone to learn both.

## Families vary one word and hold the rest still

When several types differ along a single axis, that axis is the only thing that moves in their names. The distribution configs name themselves by strategy and share the rest, so they read as one set rather than as unrelated classes. If you are adding the next member of a family, change the discriminator and change nothing else.

## Names are layered; take the nearest layer

Three conventions stack here. The module inherits Shopware-wide idioms: a DAL entity is a definition with an entity and a collection, an event listener is a subscriber, a constraint pairs with its validator. On top sit the module's own domain families. On top of those sits the behavioral-suffix system used by the validation and resolution subsystem. A new class adopts the convention of the layer it belongs to. Do not invent a naming style for a class the framework already has a word for.

## Add a new suffix only to mark a real distinction

A novel suffix is justified when it encodes a behavioral difference the generic word blurs, and the justification travels with it. The subsystem separates the service that owns a decision from the service that applies it and surfaces the resulting violations, because collapsing both under one word hides which is which. Introduce a new suffix for that kind of reason, not for variety; if an existing suffix already carries the meaning, use it.

## No exceptions

Every class in this module follows these principles, without grandfathered outliers. A name that conflicts with a principle here is a defect to correct, not a precedent to copy: the name is what changes, not the convention.
