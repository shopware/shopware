# Backward compatibility

This file is the single source of truth for backward compatibility (BC), deprecations and breaking changes in this repository. Where another page disagrees, this file wins.
The direction follows the 6.8 stability work: a major should support "lower TCO, safer upgrades, and clearer platform contracts", ship "only justified breaking changes", and "avoid breaks but enable moving forward".
Other guidelines hold the mechanics; this file links to them instead of repeating them.

## What counts as a break

A change is a meaningful breaking change if "a merchant, agency, extension partner, app developer, theme developer, integration, or hosting setup needs to change something in order to upgrade successfully".

- Judge the change by what a third party can observe, not by whether a PHP signature changed.
- Behaviour changes count. Code can stay valid while a translation, a checkout warning or a stored value behaves differently.
- Configuration and operations changes count: a removed config key, a changed default, a new required setting or runtime requirement.
- Changes to `@internal` code, private code and tests are not breaks.

Classify every externally relevant change with one type, and name the affected audience with this key: Extensions, Apps, Themes, Custom Code, Integrations, Agencies, Ops.

| Type | Meaning |
| --- | --- |
| Breaking | A contract changes or disappears, and consumers must adapt their code. |
| Deprecation | A contract announced as deprecated is removed; the replacement already exists. |
| Behaviour | Signatures stay, but observable behaviour, data or defaults change. |
| Migration | Data or references move; consumers must migrate stored data or identifiers. |
| Config/Ops | Hosting, runtime, worker, cache or configuration setup must change. |
| Internal | Nobody outside the owning team is affected; no release documentation needed. |

## When a break is acceptable

A compatibility break is acceptable only if:

1. it is intentional,
2. it has been announced through a deprecation,
3. it has a clear migration path,
4. its ecosystem impact is understood,
5. and it only takes effect in the next major version.

Accidental breaks are defects. Fix them like any other bug, in the release line where they shipped.

Keep the break proportional to its benefit. A change that saves a core team work can create upgrade work for many consumers, so weigh both costs together.

- Ask first whether an adapter or an additive change delivers the same benefit at lower combined cost.
- When usage is significant, keep a forwarding shim or a compatibility path: a forwarding template, a class alias, a delegating decorator, a fallback for the old input.
- Unknown usage is not evidence of no impact. Missing usage data is a task with an owner. If the removal is still necessary, document the trade-off and the mitigation.
- A major version is not automatically a container for breaking changes.

Answer these four questions in the PR for every break:

1. What breaks?
2. Who is affected?
3. Can we avoid the break?
4. What should we do with it: keep, make compatible, postpone, remove from the major, or discuss?

## Public API is every extension surface

Public API is every surface that external code relies on, not only the HTTP APIs. Every public extension point is a long-term compatibility promise. The surfaces are:

- PHP: classes, interfaces, traits, and public or protected methods, properties and constants that are not `@internal`. See [internal.md](internal.md) and [final-and-internal.md](final-and-internal.md).
- DI: public service ids, service aliases, arguments of supported services, and tags with a required contract.
- Events: event classes, names and payloads; extension events with their `NAME` and public properties.
- Store API and Admin API: routes, methods, request parameters, response fields, headers, status codes and error codes. DAL entity definitions are public because they map to the generic CRUD API.
- Storefront Twig: template paths, blocks, functions, filters, globals and the variables available in each block scope.
- Storefront assets: JS plugins with their names, options and emitted events; JS services and helpers; CSS selectors; SCSS variables and mixins; theme config variables; snippet keys.
- Administration: components, props, events, slots, Twig blocks, module routes, `Shopware.*` globals, services, stores and Meteor SDK position identifiers.
- App system: the manifest schema, app script hooks and the services available in them, webhook event names and payloads.
- Configuration: `shopware.yaml` keys, system config keys, environment variables and their defaults; CLI command names, options and machine-readable output.
- Feature flag names.

Adding public API gets the same scrutiny as a break. Ask: "Do we really want to support this API for the foreseeable future?"

- New extension points are the exception, not the default. Prefer an existing mechanism; see [extendability.md](extendability.md).
- Mark implementation classes `@internal` and supported concrete classes `@final` ([internal.md](internal.md#final-classes)). That every new public surface needs a named consumer is (proposed, not yet ratified).
- An explicit marker for intended public API, such as `@BCPromise`, is (proposed, not yet ratified).
- New Store API routes use extension events, not abstract route classes ([ADR](../../adr/2026-09-24-replace-abstract-route-classes-with-extension-events.md)).

(proposed, not yet ratified) Rules R1-R4: a module declares its interface instead of inheriting it from PHP visibility, and the types that cross that boundary belong to the interface and are mapped from internal types. Boundary inputs are validated at the edge with declared errors, and each new interface records its evolution strategy at design time; design review is required for any change that is "observable by a third party".

### New extension points stay open

An extension point that only core can use is not an extension point. When a feature lets extensions add parts, such as a document type, a form, a page type or a header, the same mechanism must let them add the data and behaviour that belong to those parts.

- Use a registry or tagged services instead of a closed enum, or a fixed list of core cases, in an interface that third parties implement.
- Typed request objects and DTOs that plugins fill offer the extension mechanism core uses for its own data, for example `Struct` extensions.
- A provider-style extension point lets extensions remove or override core defaults, not only add to them. Ship the core defaults through a core provider.

## Deprecation and announcement

A deprecation announces that a contract breaks in the next major. Removal is the later step. Every deprecation states:

- what is deprecated,
- the version in which it breaks (`@deprecated tag:v6.8.0`; three-part tag, not the four-part flag id),
- what to use instead,
- whether the migration is mechanical or behavioural,
- which surface is affected, and whether callers or extenders break.

Rules:

- The replacement must exist and be usable from userland (plugin, app or theme code) when the deprecation is announced. If it only ships behind a major flag, mark the deprecation `silentUntil:`.
- Executable deprecated code calls `Feature::triggerDeprecationOrThrow()`. It warns before the major and throws once the major flag is active.
- Core never triggers its own deprecations. Move core callers to the replacement. Where core must keep calling the old path, wrap the call in `Feature::silent()`.
- Use `@deprecated` only when the symbol goes away or is replaced. A symbol that stays but changes gets a BC-change attribute, never a `@deprecated reason:*` marker.
- Keep legacy behaviour in focused tests that are deleted together with the deprecation.

Mechanics live in [feature-flags.md](feature-flags.md): see "Announcing a deprecation before its replacement is stable", "Planning public API changes" and "Moving a class". PHP rules are in the [shopware-php-code skill](../../.agents/skills/shopware-php-code/SKILL.md) (Deprecations). Administration rules are in [feature-flags-and-deprecations.md](../administration/feature-flags-and-deprecations.md) and the [Admin deprecation guards ADR](../../adr/2026-08-10-administration-javascript-deprecation-guards.md).

Announce a planned change to a symbol that stays with an attribute from [`BCChange`](../../src/Core/Framework/Deprecation/BCChange/). A caller invokes the symbol, including `parent::` calls; an extender subclasses or overrides it.

| Attribute | Meaning | Breaks |
| --- | --- | --- |
| `BecomesAbstract` | Method becomes abstract; subclasses must implement it. | Extender |
| `BecomesFinal` | Class becomes `final`; subclasses must switch to composition. | Extender |
| `BecomesInternal` | Class or method leaves the BC promise. | Both |
| `BecomesReadonly` | Property becomes readonly; stop assigning from outside. | Both |
| `ClassHierarchyChange` | Parent chain changes; subclasses and `instanceof` checks adjust. | Both |
| `ClassMoved` | Class has a new name; the old name stays an alias until the version. | Both |
| `ExceptionChange` | Thrown exception types change; update catch blocks. | Caller |
| `ExperimentalReplacement` | Superseded by an experimental feature; stays under BC until then. | Both |
| `NewOptionalParameter` | Optional parameter is added; overrides must add it. | Extender |
| `NewRequiredParameter` | Required parameter is added; callers pass it now. | Both |
| `ParameterDefaultValueChange` | Default changes; callers omitting the argument see the new value. | Caller |
| `ParameterNameChange` | Parameter is renamed; named-argument callers adjust. | Caller |
| `ParameterRemoval` | Parameter is removed; callers stop passing it. | Both |
| `ParameterTypeNarrowing` | Fewer accepted types; callers passing other values adjust. | Caller |
| `ParameterTypeWidening` | More accepted types; overrides must widen too. | Extender |
| `PropertyTypeNarrowing` | Property accepts fewer types; assignments adjust. | Both |
| `PropertyTypeWidening` | Property can hold more types; reads must handle them. | Both |
| `ReturnTypeNarrowing` | Return type narrows; overrides must return the narrower type. | Extender |
| `ReturnTypeWidening` | Return type widens, for example to nullable; callers handle it. | Caller |
| `VisibilityChange` | Visibility is reduced; outside access stops. | Both |

## Flags and experimental

- A flag is not proof that a change is backward compatible. The flag-off path must behave like the previous release. The flag-on path is the next major and still needs a deprecation, a migration path and upgrade documentation.
- "Experimental" does not remove the cost for consumers who adopted it. `@experimental` code is outside the BC promise, but do not remove an experimental feature in a minor; deprecate it for the next major ([ADR](../../adr/2023-05-10-experimental-features.md)). Migrate its data; never discard it.
- Test both flag states while both paths are supported. See [feature-flags.md](feature-flags.md) ("Using flags in tests").
- Feature flag names are public API. Do not remove or rename a flag in a minor. A retired flag stays registered and does nothing.
- Record per surface whether a flag is evaluated at build time or at runtime. Changing an environment value does not rebuild Administration or Storefront assets.

## Per-surface rules

"Minor" means minor and patch releases. Everything under "Not in a minor" needs the deprecation workflow and lands in the next major.

### PHP

- Allowed: change the constructor of a DI service; constructors of services are `@internal`.
- Allowed: change anything private, `@internal` or `@experimental`.
- Allowed: add classes, constants, events and event dispatches; add public methods to `@final` classes.
- Allowed: add a method to an abstract decorator class that has `getDecorated()` ([decorator-pattern.md](decorator-pattern.md#rules-for-the-decorator-pattern)).
- Allowed: add a parameter through `func_get_arg()` and announce it with `#[NewOptionalParameter]` or `#[NewRequiredParameter]`.
- Allowed: move a class with `#[ClassMoved]` and a class alias.
- Not in a minor: remove or rename a public or protected class, method, property or constant.
- Not in a minor: change the value of a public constant. Add a new constant instead.
- Not in a minor: change a type, visibility, `static` or `final` directly. Announce it with a BC-change attribute. PHPDoc types count too: narrowing `array<string, Foo>` to `list<Foo>` breaks the PHPStan run of every plugin that relies on the old type.
- Not in a minor: add a method to an interface, or an abstract method to an abstract class.
- Not in a minor: remove an event, stop dispatching it, or remove a payload field.
- Not in a minor: rename or remove a public service id or alias; keep a deprecated alias.

### Events and decorators

Listeners and decorators depend on when core calls them and on what they see, not only on signatures.

- Not in a minor: dispatch an existing event at a different point, so that listeners see other data, or derive values from the input before the extension event that may still change it.
- Not in a minor: reset or overwrite what listeners added to a shared object, for example sortings added in a criteria event.
- Not in a minor: narrow a type hint from the abstract decorator base to the concrete core class, or branch on the exact class (`$service::class === Foo::class`). Decorators then fail or silently lose the behaviour; check an interface or a marker instead.
- When an existing event starts to fire in more situations, document it in the release notes.

### Database

Mechanics and the expand-and-contract pattern are in [database-migations.md](database-migations.md#backward-compatibility).

- Allowed: add tables, nullable columns, columns with defaults, and indexes in `update()`.
- Allowed: add entity fields and associations.
- Allowed: add a new column, copy the data, and keep the old column until the major.
- Not in a minor: drop or rename a table or column. Destructive steps go into `updateDestructive()` of the next major.
- Not in a minor: break blue-green deployment. The previous release must still run on the new schema.
- Not in a minor: remove or rename an entity field, or make an optional field required. This also changes the Admin API.
- Never: change a released migration, or overwrite customized data ([rules 1 and 5](database-migations.md#important-rules)).

### Store and Admin API

- Allowed: add routes, optional request parameters and response fields.
- Allowed: change the PHP controller class. The route contract is public; the controller is `@internal`.
- Allowed: mark a new route `Experimental` in its OpenAPI schema while the feature is experimental.
- Not in a minor: remove or rename a route, HTTP method, parameter, header or response field.
- Not in a minor: add a required parameter, or narrow the accepted values.
- Not in a minor: change a field type, its nullability, or the response shape.
- Not in a minor: change status codes or error codes that clients branch on.
- Not in a minor: change defaults that alter results, such as sorting, limits, loaded associations or write scopes.
- Never: add or change a core route without its OpenAPI schema.

#### Schema precision

Clients and SDKs are generated from the OpenAPI schema, so an imprecise schema is a broken contract even when the PHP code is right.

- Mark every response field that is always sent as `required`, even when its value can be empty. Mark a request field `required` only when the route rejects requests without it.
- Use `enum` or `const` for fixed values. Avoid `additionalProperties` on shapes that clients read; extension data belongs in the `extensions` field.
- Describe what each field means and when it is present, and update the description when the meaning changes.
- Define the 4xx responses of every route: `401` when the caller is not authenticated, `403` when an authenticated caller lacks permission.
- State whether a parameter is read from the query or from the body, and let a schema pattern accept exactly the values the server accepts.
- Mark a deprecated route or field as `deprecated` in the schema.

### Storefront (Twig, JS, SCSS)

- Allowed: add templates, blocks that do not change variable scope, variables, JS plugins, plugin options with defaults, and events.
- Allowed: change a Twig variable's value while keeping its type; move a variable definition up to an outer block.
- Allowed: change purely visual CSS properties.
- Not in a minor: remove, rename or move a Twig block. Any move breaks theme overrides. Deprecate with `{% deprecated %}`; for a rename, wrap the old block in the new one.
- Not in a minor: wrap existing content in a new block that changes the scope of variables.
- Not in a minor: remove or rename Twig variables, functions, filters or templates, or change a variable's type.
- Not in a minor: remove HTML sections, or move them into another block.
- Not in a minor: rename or remove JS plugins, services, helpers, public methods, options or events, or change event parameters.
- Not in a minor: rename or remove CSS selectors (including `is--*`), SCSS variables, mixins, theme variables or snippet keys.
- Not in a minor: change structural CSS properties: `display`, `position`, `visibility`, `z-index`, `pointer-events`, `overflow`, `transform`.

### Administration

- Allowed: add components, optional props with defaults, slots, blocks, events and methods.
- Allowed: add optional trailing parameters, or new keys to a destructured object parameter.
- Allowed: change `@private` and `_`-prefixed members freely ([ADR](../../adr/2026-08-10-administration-javascript-deprecation-guards.md)).
- Not in a minor: rename or remove components, props, slots, Twig blocks, events, methods, module routes or route parameters.
- Not in a minor: add a required prop. Add it as optional and warn when it is missing.
- Not in a minor: change `ref` attributes, `v-if`, `v-model` or `v-bind` bindings that overrides rely on.
- Not in a minor: change the public API of `Shopware.*`, registered services or stores.
- Not in a minor: rename or remove position identifiers, assets or imports, functional selectors or root CSS selectors.
- Not in a minor: stop calling a method or service that plugins override, for example by switching core to a new method or by ignoring an argument of a documented call signature. The override still loads, but its effect is gone.
- Mark new components, services and methods `@private` until their API is meant to be public. Everything else is public from its first release.
- Put logic in methods or computed properties, not in template expressions, so that overrides can change it through `$super`.

### App system

- Allowed: add optional manifest elements and attributes.
- Allowed: add webhooks, app script hooks, script services and service methods.
- Not in a minor: add a required manifest element, or remove or rename an element.
- Not in a minor: make manifest validation stricter for existing apps.
- Not in a minor: remove or rename webhook events or payload fields.
- Not in a minor: remove or rename app script hooks, script services or their methods.
- Not in a minor: change install, update or uninstall behaviour that apps rely on, such as custom field identity or precedence.
- Keep one manifest working on the current and the next major.

### Configuration and operations

- Allowed: add config keys, environment variables and CLI options whose defaults keep the current behaviour.
- Allowed: add a config key to `config-schema.json` together with `shopware.yaml`. Declare every new key with its default in the shipped package configuration, not only in the test configuration, so that operators can find it.
- Not in a minor: remove or rename a config key or environment variable. Keep reading the old name until the major.
- Not in a minor: change a default that changes behaviour.
- Not in a minor: require new infrastructure or setup, such as a new messenger transport to consume or a database setting.
- Raising the minimum PHP, database or search engine version is a hosting requirement change: announce it in `RELEASE_INFO` at least one minor ahead and document it in the system requirements; never ship it silently.
- Not in a minor: rename or remove CLI commands, options or machine-readable output fields, change exit codes, or make an existing invocation fail or behave differently.
- A new scheduled task changes what runs in every shop. Announce it in `RELEASE_INFO` with its interval and how to switch it off; `@experimental` tasks are no exception.

## Enforced by CI

These checks run on every PR. Do not re-check by hand what they catch; review what they cannot see.

| Check | What it catches |
| --- | --- |
| `bc-checker` job ([php.yml](../../.github/workflows/php.yml)) | Roave BC check of PHP signatures against the last release tag or the PR base. Skips `@internal`; accepted breaks are listed with a reason in `.bc-exclude.php`. |
| `InternalClassRule` | Test classes, Storefront controllers, bundles, compiler passes and subscribers must be `@internal` (subscribers may be `@final`). |
| `InternalMethodRule` | DI service constructors must be `@internal`; no deprecation annotation on them. |
| `DeprecatedMethodsThrowDeprecationRule` | Deprecated methods and classes must call `Feature::triggerDeprecationOrThrow()`. |
| `DeprecatedServiceDecoratorPattern` | Deprecated decorators must warn, or delegate directly once the flag is active. |
| `DeprecatedServiceFeatureTagRule`, `DeprecatedServiceDefinitionFeatureTagRule` | Deprecated services are removed with their flag via `shopware.inactiveFeature`. |
| `BCChangeAttributeUsageRule` | BC-change attributes describe a possible change and add the runtime signal where detectable. |
| `NoBCPlanningDeprecationRule` | No `@deprecated reason:*` markers; use BC-change attributes. |
| `FuturePropertyCompatibilityRule`, `FuturePropertyVisibilityChangeRule` | Core code that conflicts with an announced property change. |
| `NoClassAliasUsageRule`, `NoClassAliasExpressionUsageRule` | Core code using the old name of a moved class. |
| `FeatureFlagVersionRule` | Version-shaped flag ids must have four parts. |
| `StoreApiRouteExtensionRule` | New Store API routes use extension events. |
| `AnnotationTagTest` (`tests/devops`) | Malformed or stale `@deprecated` tags, `@experimental` without `stableVersion`, stale BC-change versions and `silentUntil` markers. |
| Danger `RemovedTwigBlocks` | Warns when a Storefront Twig block was moved or removed. |
| Danger `RouteSnapshotExtension` | Fails when a new route is added to the routes-without-schema snapshot. |
| Danger `MissingReleaseInfo`, `ShopwareYamlConfigSchemaHint` | Missing release notes; `shopware.yaml` changes without a `config-schema.json` update. |
| `ApiRoutesHaveASchemaTest` | Core API routes without an OpenAPI schema, method mismatches and stale schema entries. |
| OpenAPI snapshot bot (`openapi-lint` job) | Posts the Store and Admin API schema diff on the PR; Redocly lint and the Store API schema migration report fail on mismatches. |
| ESLint `require-position-identifier` | Administration components that need a Meteor SDK position identifier. |

CI does not catch behaviour changes, changed defaults, Twig variable scope, Administration and Storefront JS API changes, or HTTP response semantics. Review those by hand.

## Documenting a break

Every deprecation, break and externally relevant behaviour or configuration change gets release documentation in the same PR. Follow the [shopware-release-docs skill](../../.agents/skills/shopware-release-docs/SKILL.md) for what to write and where, and [documenting-a-release.md](../../delivery-process/documenting-a-release.md) for the process. This file does not restate their rules.

## Sources

Confluence pages (titles and page ids; CI cannot open them):

- Shopware 6.8 Major Release (22143139860)
- 6.8 Plan (22252552193)
- 6.8 Kick-Off (21951840291): working definition of a meaningful breaking change, the four questions
- Breakage Review for Version 6.8 (21971369995): change types and audience key
- RFC: Reducing the Impact and Frequency of Breaking Changes (22032515084): acceptance conditions, deprecation metadata
- "Smaller": Towards Intentional Public APIs (21996994570)
- Workshop stability (22257533344), with Deprecation Workflow (22257434788), Breaking-change cases (22258843664) and Feature flags (22258286709)
- API Design as a Stability Strategy (22312550601): rules R1-R4, proposed

Repository files:

- [internal.md](internal.md), [final-and-internal.md](final-and-internal.md), [feature-flags.md](feature-flags.md), [decorator-pattern.md](decorator-pattern.md), [extendability.md](extendability.md), [database-migations.md](database-migations.md)
- [Administration feature flags and deprecations](../administration/feature-flags-and-deprecations.md)
- [shopware-php-code skill](../../.agents/skills/shopware-php-code/SKILL.md), [shopware-release-docs skill](../../.agents/skills/shopware-release-docs/SKILL.md)
- [ADR: extension events for Store API routes](../../adr/2026-09-24-replace-abstract-route-classes-with-extension-events.md), [ADR: Administration deprecation guards](../../adr/2026-08-10-administration-javascript-deprecation-guards.md), [ADR: experimental features](../../adr/2023-05-10-experimental-features.md)
- `src/Core/Framework/Deprecation/BCChange/`, `src/Core/DevOps/StaticAnalyze/PHPStan/Rules/`, `src/Core/DevOps/StaticAnalyze/Danger/Rules/`, `.github/workflows/php.yml`, `.bc-exclude.php`

Superseded public page, kept for its code examples only: https://developer.shopware.com/docs/resources/guidelines/code/backward-compatibility.html
