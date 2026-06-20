# Binding

Defines the `LayoutBindingEnumerator` extension point and its Core implementation. Enumerators enumerate every distinct source binding of a content layout so the well-formedness write-validator can re-check an already-bound layout against each source it is bound to.

## Key Classes

- `LayoutBindingEnumerator` — interface tagged `content_system.layout_binding_enumerator`; implement and tag to add a new binding source; `enumerate(string $contentLayoutId, Context $context): array` returns `list<SourceBinding>` — return an empty list when the layout has no bindings, including a not-yet-persisted layout id on the CREATE path, where `Validation/ContentLayoutWriteValidator` passes a brand-new id with zero existing assignments
- `SourceBinding` — `@internal` value object carrying `$sourceId` (a source identifier — the bound entity type for entity bindings, e.g. `product`/`category`, or the section name `header`/`footer`) and `$providedRootContext` (`list<ProvidedContext>`); consumed by `Validation/LayoutGate`
- `EntityAssignmentBindingEnumerator` — Core implementation; iterates registered `AbstractContentLayoutAssignableDefinition` types and yields one binding per type that has any assignment row for the layout

## Known Implementations

| Class | Module | Binding sources |
|---|---|---|
| `EntityAssignmentBindingEnumerator` | Core | product, category, landing-page (any `AbstractContentLayoutAssignableDefinition`) |
| `HeaderFooterBindingEnumerator` | Storefront (`ContentSystem/Validation/`) | header, footer (empty root context) |
