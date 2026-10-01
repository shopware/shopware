# Binding Specifications

The module-root view of the binding specification system: what one declaration wires, and the two modes
in which it is applied to an element.

- **Binding Specification System**: `Binding/Specification/BindingSpecification` — a declaration wiring one element type's reference properties to data loaders (`resolves`) and seeding its primitive properties (`inputs`), authored inline in an element-type YAML file's optional `bindings:` map or synthesized from the type's `resolvedBy` reference properties. `Binding/BindingApplicator` is the merge that applies one onto an element, in overwrite (`bind-element`, an explicit `bindingSpecificationId`) or fill-only mode (a type's auto-applied default at scaffold and replace), and `Binding/AttributionReconciler` re-derives `attributedSpecifications` at the `content_layout` write boundary. Published as the `bindingSpecifications` fold on each type entry of `content-system-element-types.json`. The whole subsystem — symbols, loaders, registry, validators, app persistence, the default rules — is owned by [Binding/AGENTS.md](../Binding/AGENTS.md) and its `../Binding/docs/`; do not grow this summary into a second copy of it
