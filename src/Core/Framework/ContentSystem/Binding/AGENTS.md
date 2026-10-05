## Navigation

- Why app-shipped declarations are reconciled, never patched: [extension-surface.md](../docs/principles/extension-surface.md)

## Constraints

- Uniqueness is by source-qualified id (`"source:id"`), not by bare id: `ContentSystemBindingSpecificationRegistry::all()` keys on `BindingSpecification::qualifiedId()`, so only a duplicate **within** one source/directory throws `bindingSpecificationDuplicate`
- **App type overlay:** the app persister and validator pass a type overlay built from the app's own `Resources/content-system/types`; every non-app path passes an empty overlay
- **Default uniqueness** is at most one default specification per element type (`isDefault()` derives `id === type`, computed, never stored). An authored `bindings:` key equal to the file's type name is rejected before duplicate detection (`bindingSpecificationReservedId`, 409). At *application* time the default set is read zero/one/more: no-op / fill-applied / `bindingSpecificationDefaultAmbiguous` (409)
- `TypeConsistentBindingSpecification` resolves each `resolves` entry's produced type via `Diagnostics/RootContextMapper::resolveType()`; a client-defect exception becomes a validation violation, any other propagates
- A DECLARATION stays scalar: `WellFormedBindingSpecification` admits a scalar or `null` `inputs` default and nothing else
- Loaders are tagged `content_system.binding_specification_loader`. Core ships no dedicated binding-specification directory and no authored inline `bindings:` entry: every core binding specification is a synthesized default, seven in all, each from the `resolvedBy` properties of one file under `Layout/Type/Definitions/`. Inventory: [docs/default-specification.md](docs/default-specification.md)
- `DatabaseBindingSpecificationLoader` validates each row independently (identified by `source:id`); a row whose schema fails to decode or validate is skipped and logged at `warning` level rather than failing the whole load
- Reconcile app rows as `ContentSystemBindingSpecificationPersister::persist()` does. Override every lifecycle hook in the handler. Check: `ContentSystemBindingSpecificationPersisterTest` pins the lock and the transaction.
