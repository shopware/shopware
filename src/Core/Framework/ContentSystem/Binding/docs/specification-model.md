# Binding Specification Model

What a binding specification declares, what it is deliberately not, and why the subsystem repeats a shape rather than sharing one.

## Not a Root Source

"Binding" names a different relationship than "root source" (`Adapter/RootSourceRegistry`): a root source is the registered origin of a layout's root-ambient context (an entity type, a section, or "none"); a binding is the relationship between one reference property and the source that fills it — the sense `Diagnostics/ViolationScope::Binding` already carries. A `BindingSpecification` authors such a binding for one element type; it says nothing about what a layout's root is bound to. See [NAMING.md](../../NAMING.md).

## The Specification Model

- `BindingSpecification` — the immutable declared contract of one binding: its `id`, the element `type` it applies to, a human `label`, a `resolves` map (reference property key → `LoaderBinding`), and an `inputs` map (primitive property key → `BindingInput`). `toSchema()` serializes it for introspection.
- `LoaderBinding` — one `resolves` entry: a data loader `source` plus its `config`. Becomes a `Layout/Element/DataRequirement/DataRequirement` when applied to an element.
- `BindingInput` — one `inputs` entry: an optional typed default for a primitive property, with presence modeled explicitly (`hasDefault()`) so "no default" is distinct from "default is null".

A specification's `resolves`/`inputs` keys are validated at load time against the declared type's actual properties, so an applied specification can never target a property the type does not have.

## Design Note: Deliberate Duplication

This subsystem does not share code with `Layout/Element/Style/` beyond the pattern each class follows (loader trio, decorated registry, compiler pass, app tier). Each system's declaration validates against a different live registry and produces a different runtime artifact (a `DataRequirement` and seeded properties here, an `ElementStyle` there), so collapsing the two behind a shared abstraction would couple two independently evolving vocabularies for a structural resemblance only. Repeat the shape; do not factor it out.

## Binding Specifications

Hand-assembling a data requirement means naming the right loader, the right config keys, and a property the element's type actually declares. A binding specification is a pre-validated wiring authored alongside the element type that does this in one step: applying it (via the `bind-element` action, or an `insert-element` action carrying a `bindingSpecificationId`) writes the specification's data requirements onto the element and seeds defaults for primitive properties the element does not already set.

The available specifications for each element type are folded into `GET /api/_info/content-system-element-types.json`. Applying one records which specification wired which key in the element's `attributedSpecifications` map; the system re-derives this bookkeeping on every save and drops an entry whose wiring was later hand-edited.

Authoring specifications is an extension concern, covered in [custom-specifications.md](custom-specifications.md); the admin-facing introspection surface is covered in [introspection.md](introspection.md).
