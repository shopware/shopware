# Extension surface

The extension surface is the set of classes, declarations and events that a plugin or app may build on.

## The extension surface is a deliberate, bounded, listed choice

The module bounds its surface against Hyrum's law and keeps every extension point that it does not list internal. The listed surface holds the two [tree-replacement events](rendering.md#rendering-stages-are-direct-calls-handing-each-other-typed-immutable-results), the base classes a tagged service extends, the declaration directories, app declarations, and the explicitly supported mapping registry decoration target. The module makes an extension point public only after a check that the point delivers what it claims. A documented offer does not justify keeping a defective extension point. An extension point with no core consumer stays when its consumers are external or planned.

Why: A broken offered extension point is worse than no extension point.

In code:

- `InternalClassRule::CONTENT_SYSTEM_PUBLIC_SURFACE` lists the offered classes.
- The same list names `RenderedTreeEditor`, which has no core caller.
- `ContentPipelineTest` pins that a listener on either event replaces the tree the pipeline carries on.
- See [extending.md](../extending.md).

Intentional mapping overrides decorate public `AbstractContentSystemMappingCandidateRegistry`. Its final `resolveSource()` admits exact sources and records metadata dependencies before a protected override hook. Concrete registries and built-in providers remain internal. New plugin sources use tagged providers; app-based provider registration is outside the contract. See [data-mapping principles](data-mapping.md#intentional-overrides-cannot-skip-source-admission).

## InternalClassRule enforces publicness positively

A public signature must name only public types. Every class in a `ContentSystem` namespace is internal unless the public-surface list names it. An omission from the list therefore cannot publish a class. The public-surface list must also name every module type that appears in the signature of a public member of a listed class.

Why: A public member whose signature names an internal type forces a plugin to reference that type. Marking chosen classes internal publishes every class the marking misses.

Exceptions: The test builders under `src/Core/Test/Stub/` are public without an entry in the public-surface list.

In code:

- `InternalClassRule` requires `@internal` on each enrolled class that `CONTENT_SYSTEM_PUBLIC_SURFACE` does not name.
- It reads classes, not signatures.
- No check therefore enforces the rule on public signatures.
- See [internal.md](../../../../../../coding-guidelines/core/internal.md).

## An app-shipped declaration is validated and reconciled by the module, never trusted or patched

A registry must reconcile app rows under a per-app lock and in one transaction. It must invalidate its cache inside the lock. The lifecycle handler of the registry must implement every lifecycle hook. Install and update must fail on a malformed declaration and on a nameless or malformed row. Binding ids are unique per source. An uninstall locks no layout.

Why: A malformed row that install accepts fails every load of its registry. A lifecycle handler without a deactivate hook still serves a deactivated app. A binding is scoped to its element type, not a Store API wire key.

Decided against: A global flat id namespace, as the style-option and element-type names use.

Exceptions: The attribution reconciler drops an attribution to a specification that no longer exists, and the drop is not a fault.

In code:

- `ContentSystemBindingSpecificationPersister::persist()` enforces the rule.
- See [loading-and-apps.md](../../Binding/docs/loading-and-apps.md).

## A guard on listener output never throws on an action that the extension contract permits

Where no sound runtime guard on listener output exists, the requirement is a documented contract, not a guard that handles one path and misses another.

Why: A guard based on the one producer that surfaced a hazard turns a known limitation into an assumed protection.

In code:

- `ContentPipeline::load()` serves an element that a finalization listener adds.
- A repeated id is the one listed edit that fails, under the [render checks](rendering.md#the-render-validates-the-whole-stored-forest-in-every-mode).
- See [custom-listeners.md](../../Event/Listener/docs/custom-listeners.md).

## An app or plugin extends the module through declared data the module reads

The module applies the "open/closed principle" to element capabilities. An extension adds a capability as an element type, a style option or a binding specification that the module reads. An app's rows hold the declaration as raw JSON, which round-trips without a migration. The module does not add a capability that needs an edit to a core class per entity or per element. For a capability that needs a per-entity or per-element core edit, the module makes the framework change once, for every element. An extension developer can then use that framework change alone.

Why: A capability that needs a core edit per entity is closed to apps, and each further entity needs another core edit.

Decided against: Core classes that call app code.

Exceptions: `ProductContentLayoutDefinition` adds the `reviews` page data requirement and `CategoryContentLayoutDefinition` adds `productListing`, each per entity, until a type-declared page-data mechanism replaces them. Sibling elements share one load only through [root context](README.md#glossary), and today only a core definition registers page data for it.

In code:

- `ContentSystemCompilerPass` finds the types directory of a plugin without a service registration.
- `ContentSystemElementTypePersister` writes the type of an app to a `schema` JSON field.
- `ElementTypeSpecificationSerializerTest` pins that a declaration round-trips through its JSON schema unchanged.
- See [custom-types.md](../../Layout/Type/docs/custom-types.md).

## The module adds nothing to a platform base class plugins extend

The module adds no protected member, subscribed service or import to `StorefrontController` or any other platform base class that plugin controllers extend. A controller that needs the content route takes `AbstractContentRoute` as a constructor argument and keeps its own private helper.

Why: Plugin controllers inherit every member of their base class, so plugins can call a shipped member and a change to it breaks those plugins.

Exceptions: StorefrontController keeps `loadContentPage()`, the subscribed service and the imports until separate work reverses the addition. `ProductController` and `NavigationController` call `StorefrontController::loadContentPage()`.

In code:

- `ContentRouteCompilerPass` aliases `AbstractContentRoute` to the main section's route in the full format.
- No check enforces the rule.
- See [SalesChannel/README.md](../../SalesChannel/README.md).
