## Constraints

- Key binding specifications by `qualifiedId()` (`source:id`); throw `bindingSpecificationDuplicate` only for a duplicate within one source. Check: does one bare id from two sources still coexist? [custom-specifications.md](docs/custom-specifications.md#collision-detection)
- Reject an authored `bindings:` key equal to the file's type name with `bindingSpecificationReservedId`, before duplicate detection. Check: does such a key raise the reserved-id error, not the duplicate error? [resolved-by.md](docs/resolved-by.md)
- Keep a declaration's `inputs` default scalar or `null` in `WellFormedBindingSpecification`. Check: does an array default still fail validation? [validation.md](docs/validation.md)
- Reconcile app rows as `ContentSystemBindingSpecificationPersister::persist()` does; override every lifecycle hook in the handler. Check: does a deactivated app's specification leave the registry? [extension-surface.md](../docs/principles/extension-surface.md#an-app-shipped-declaration-is-validated-and-reconciled-by-the-module-never-trusted-or-patched)

## Where to look

- Class inventory per directory: [README.md](README.md#subdirectories)
- What a specification declares and what "binding" is not: [specification-model.md](docs/specification-model.md)
- Authoring a specification as a plugin or app: [custom-specifications.md](docs/custom-specifications.md)
- `resolves` authoring tiers and canonicalization: [authoring-sugar.md](docs/authoring-sugar.md)
- Entity-name derivation turning ambiguous after an install: [entity-name-derivation.md](docs/entity-name-derivation.md)
- `resolvedBy` and its synthesized default: [resolved-by.md](docs/resolved-by.md)
- Default derivation, uniqueness and application-time ambiguity: [default-specification.md](docs/default-specification.md)
- Core's synthesized defaults: [default-specification.md](docs/default-specification.md#the-core-defaults)
- Inline `bindings:` entries in a type file: [inline-bindings.md](docs/inline-bindings.md)
- App type overlay of the app persister and validator: [inline-bindings.md](docs/inline-bindings.md#inline-bindings-in-an-app-the-type-overlay)
- Load-time validators and produced-type resolution: [validation.md](docs/validation.md)
- Loaders, loader tag, registry, compiler pass, app lifecycle, malformed database rows: [loading-and-apps.md](docs/loading-and-apps.md)
- Applicator modes and the mutation operations: [applying.md](docs/applying.md)
- Attribution reconciliation on DAL writes: [write-boundary.md](docs/write-boundary.md)
- The `bindingSpecifications` catalog clients read: [introspection.md](docs/introspection.md)
