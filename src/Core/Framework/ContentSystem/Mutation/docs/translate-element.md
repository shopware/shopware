# TranslateElement

`__construct(AbstractContentSystemElementTypeRegistry $registry, string $elementId, array $values)`.

Replaces the language map of each key in `$values` on one element, writing each map as supplied in its stored shape;
every property key not in `$values` carries verbatim, unread. The key gate bounds what the operation can change: only
a property the element's type declares translatable is reachable, never another property, wiring, style or child.
Each key is cast to string before the lookup (`(string) $key`), so a key PHP turned into an integer array key is
reported like any other key.

Rules, in this order, each a `400` apart from the known limitation in
[mutation-errors.md](../../Api/docs/mutation-errors.md):

1. Target must exist (`mutationTargetNotFound`).
2. The element's component must be registered (`mutationUnknownType`, via `requireRegistered`).
3. Every key in `$values` must name a declared property whose `PropertyType::translatable()` is true
   (`mutationPropertyNotTranslatable`, carrying the element id and the first offending key; an undeclared key fails
   the same way).
4. Each map must satisfy `PropertyType::admits()` for its declared type (`mutationPropertyValueRejected`).
5. Every key of each map must be a language id in lowercase UUID hex (`mutationPropertyLanguageKeyInvalid`).

The language-key check runs in a pass after every map is admitted, so a value rejection on any key reports ahead of a
language-key rejection on another. Key existence stays a `dangling_language` diagnostics warning.

The operation's own edit normalizes nothing and overlays no type default. There is no anchor rule: a map without the
`Defaults::LANGUAGE_SYSTEM` entry passes the operation. On the draft route, a required property left without its
anchor surfaces as `UnresolvedRequired` in the response diagnostics only when the request names a `rootSource`. On
the persisted route the committing write rejects it as a `400`. There is no `$removeKeys`: clearing one language is
writing the map without that entry, and removing a property key stays with `UpdateElementProperties`.

`affected = [elementId]`; `created`, `orphaned`, `droppedWiring` and `droppedProperties` stay empty.

`writePrivilege()` returns `content_layout:translate`. `PersistedLayoutMutator::mutate()` checks that the caller
holds it before reading anything (`ContentSystemException::missingPrivileges`, `403`, ahead of any `404`, `409` or
`400`) and runs the repository `update()` in `Context::SYSTEM_SCOPE` ([runners.md](runners.md#persistedlayoutmutator)), where `AclWriteValidator`
checks no privilege, so the persisted route needs `content_layout:translate` and not `content_layout:update`.
Listeners running inside that write (`EntityWriteEvent`, `PreWriteValidationEvent`, `PostWriteValidationEvent`) see
system scope. `EntityWrittenContainerEvent` subscribers run in system scope on every DAL write already, since
`EntityRepository` dispatches it inside `Context::scope(Context::SYSTEM_SCOPE, ...)`, so the operation adds no
exposure there. The shape follows `UserController::updateMe`: a route privilege, a field allowlist (here the
translatable-key gate) and a system-scope write. Where a loader `PropertyReference` names a translatable property,
each language-map entry selects the entity that loader loads, so grant the privilege there on the trust terms of
layout editing. The key gate above confines the operation's own edit. The persisted write, like every layout write,
passes the whole tree through `Layout/LayoutWriteBoundary`
([layout write gates](../../docs/layout-write-gates.md)), which seeds absent primitive type defaults, canonicalises
every element's style and re-derives every element's `attributedSpecifications`, on elements the operation did not
touch as well.
