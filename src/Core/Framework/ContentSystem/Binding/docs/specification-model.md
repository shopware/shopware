# Binding Specification Model

## Not a Root Source

"Binding" names a different relationship than "root source" (`Adapter/RootSourceRegistry`). A root source is the registered origin of a layout's root-ambient context (an entity type, a section, or "none"). A binding is the relationship between one reference property and the source that fills it. `Diagnostics/ViolationScope::Binding` already carries this sense. A `BindingSpecification` authors such a binding for one element type. It says nothing about what a layout's root is bound to. See [NAMING.md](../../NAMING.md).

## The Specification Model

- `BindingSpecification`: the immutable declared contract of one binding. It carries `id`, the element `type` it applies to, a human `label`, a `resolves` map (reference property key → `LoaderBinding`), and an `inputs` map (primitive property key → `BindingInput`).
- `LoaderBinding` — one `resolves` entry: a data loader `source` plus its `config`. Becomes a `Layout/Element/DataRequirement/DataRequirement` when applied to an element.
- `BindingInput` — one `inputs` entry: an optional typed default for a primitive property, with presence modeled explicitly (`hasDefault()`) so "no default" is distinct from "default is null".

A specification's `resolves`/`inputs` keys are validated at load time against the declared type's actual properties, so an applied specification can never target a property the type does not have.

## Design Note: Deliberate Duplication

This subsystem does not share code with `Layout/Element/Style/` beyond the pattern each class follows (loader trio, decorated registry, compiler pass, app tier). Each system's declaration validates against a different live registry and produces a different runtime artifact (a `DataRequirement` and seeded properties here, an `ElementStyle` there). Collapsing the two behind a shared abstraction would therefore couple two independently evolving vocabularies for a structural resemblance only. Repeat the shape; do not factor it out.

## Binding Specifications

Applying a specification, through the `bind-element` action or an `insert-element` action carrying a `bindingSpecificationId`: [applying.md](applying.md).

Where the specifications are listed and how attribution stays accurate across saves: [introspection.md](introspection.md), [write-boundary.md](write-boundary.md).
