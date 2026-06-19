@README.md

## Source Code References

- `LayoutBindingEnumerator` — interface; extension point for enumerating all distinct source bindings of a layout; tagged `content_system.layout_binding_enumerator`
- `BoundRootContext` — value object; one source binding of a layout with its `list<ProvidedContext>` root-context set; `@internal`
- `EntityAssignmentBindingEnumerator` — Core implementation; yields one `BoundRootContext` per assignable definition type that has at least one assignment row for the layout. Each emitted binding's `$sourceId` is `$definition->getContentLayoutEntityType()` and its `$providedRootContext` is derived via `RootContextMapper::map($definition->getPageDataRequirements())` (the same shared mapping path as `Adapter/FactoryHelper/EntityLayoutContextFactory::providedRootContext()`); `@internal`

## Constraints

- Tag `content_system.layout_binding_enumerator` — consumed via `tagged_iterator` by `Validation/ContentLayoutWriteValidator`; add the tag to register a new enumerator
- `LayoutBindingEnumerator::enumerate()` PHP return type is `array`; docblock contract is `list<BoundRootContext>` — implementations must return a list (no string keys)
- `BoundRootContext::$sourceId` is the source identifier — the bound entity type for entity bindings (e.g. `product`, `category`) or the section name for storefront bindings (`header`, `footer`); `$providedRootContext` is the ambient context the source supplies to the layout's top-level elements
- `EntityAssignmentBindingEnumerator` emits one binding per *distinct assigned type*, not per assignment row — bounded by `AbstractContentLayoutAssignableDefinition` instances in the `DefinitionInstanceRegistry`
- Storefront registers `HeaderFooterBindingEnumerator` (`Shopware\Storefront\ContentSystem\Validation\`) with the same tag; it emits bindings with an empty `$providedRootContext` (header/footer sections carry no ambient entity context)
- Related: `Validation/LayoutResolvabilityValidator::isBindingEnforced(BoundRootContext)` is the consumer of each `BoundRootContext`; `Resolution/ProvidedContext` is the element type of `$providedRootContext`
