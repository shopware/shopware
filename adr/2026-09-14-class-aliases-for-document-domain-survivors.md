---
title: Class aliases for the document domain refactor survivors
date: 2026-09-14
area: after-sales
tags: [core, documents, deprecation]
---

## Context

[2026-08-05-document-generation-v1-to-v2-migration-strategy.md](https://github.com/shopware/shopware/blob/trunk/adr/2026-08-05-document-generation-v1-to-v2-migration-strategy.md)
decided that 13 classes of the legacy document domain survive the v1 removal and move into the `DocumentV2`
namespace. It scheduled that move for 6.9, together with the deletion of v1.

Deferring the move to 6.9 keeps a fuzzy public API for two majors: `DocumentV2` reaches into
`Shopware\Core\Checkout\Document` for its entities, definitions and its `RenderedDocument`, so neither namespace
describes a domain on its own. Moving without a compatibility layer is a break in a minor, which the backwards
compatibility promise does not allow.

Two alternatives were rejected in DL-0003: a `NamespaceChange` attribute, which still forces every consumer to rename
at one specific moment, and duplicating the classes, which means maintaining two copies that drift for two majors and
is impractical for entity definitions.

Shopware has no precedent for class aliases: there is no `class_alias` call anywhere in `src/` today, and the
comparable move in 6.7, which relocated `NotificationEntity`, `NotificationDefinition` and `NotificationCollection`
from the Administration bundle into the Core, was a plain break announced in `UPGRADE-6.7.md`. This decision
deliberately departs from that precedent, because that move did not happen in the middle of a two-major migration
window in which extensions have to address both implementations.

## Decision

All 13 classes move into `Shopware\Core\Checkout\DocumentV2` in 6.7.15.0. The twelve that are public API keep their
previous fully qualified names usable until 6.9 through class aliases. `ReferenceInvoiceLoader` is class-level
`@internal`, carries no promise, and simply moves.

`DocumentRoute` keeps throwing the v1 `DocumentException`. Its error codes are the documented Store API contract, and
`DOCUMENT_V2__*` is not `DOCUMENT__*`, so changing the exception class is a larger break than the namespace and waits
for 6.9. `DomainExceptionRule` would otherwise require `DocumentV2Exception`, so the route is listed in that rule's
`REMAPPED_DOMAINS`, the same escape hatch `Kernel` and the migration importers use.

### The alias is declared by the class that survives

Each moved class ends with a `class_alias()` call naming its previous fully qualified name. The alias is therefore
registered as a side effect of loading the class itself, and no bootstrap hook is needed.

This placement is what makes the aliases reliable. PHP never consults the autoloader when it verifies a type: against
an unregistered name, `instanceof` returns `false` and parameter, return and property type checks raise a `TypeError`,
with no autoload attempt and no diagnostic naming the cause. Declaring the alias from the surviving class removes that
failure mode by construction, because an instance of that class cannot exist unless its file was loaded, and loading
it registered the alias. Every type check that could succeed does.

Registering the aliases eagerly from a file in `autoload.files` was considered and rejected. It costs 15 additional
file loads in every process, it is invisible to every tool including our own deprecation checks, and Composer
`require`s those entries, so a stale path is a fatal error at bootstrap for the whole application rather than a
degraded feature.

### A shim keeps the previous path resolvable

References that name a class without holding an instance — `new`, a constant, a static call, `extends` — do trigger
the autoloader, so a file has to remain at the previous PSR-4 path. It carries no logic, only a `class_exists()` call
on the surviving class, which registers the alias.

The shim additionally declares the previous name inside an `if (!class_exists(...))` guard, extending the surviving
class. The guard never passes, because the `class_exists()` call above it has already registered the alias, so the
declaration is unreachable and the alias is unaffected. It exists to make the previous name visible to IDEs and static
analysis. A guard was chosen over a plain `if (false)` because the latter reports `if.alwaysFalse` and would need
suppressing.

This is why `RenderedDocument` is no longer `final`: a logic-only shim leaves the previous name undeclared, which the
BC checker reads as a removed class and which resolves to *class not found* under a classmap-authoritative autoloader.

Each shim carries the `@deprecated tag:v6.9.0` notice on the declared class, and so does every `class_alias()` call.

### Service ids are aliased explicitly

`Legacy\Foo::class` is resolved by the compiler and yields the previous string, while `get_class($object)` yields the
current one. Everything keyed by a class-name string therefore needs its own alias. The service definitions move to
the new fully qualified names and each previous id becomes a public Symfony alias. Public matters: `EntityCompilerPass`
marks the tagged definition service public but not its aliases, and `DefinitionInstanceRegistry::get()` resolves
through `ContainerInterface::has()`.

Entity names are untouched, so repository ids, `apiAlias`, the API routes and every `EntityExtension` keep working
without changes.

## Consequences

Extensions that import the previous names keep working at runtime for the whole 6.7 and 6.8 line, including
`instanceof` checks and type declarations against them, and including extensions that address v1 and v2 side by side.
The namespaces describe their domains from 6.7.15.0 onwards, no code is duplicated, and nothing is added to the
bootstrap path.

Static analysis models the shim as a subclass of the surviving class, while at runtime the two names are the same
class. An extension that keeps a previous type declaration and receives a v2 instance therefore gets an
`argument.type` report from PHPStan even though the code is correct. Renaming the import resolves it, which is the
intended migration.

`ReferenceInvoiceLoader` moves without an alias, so a reference to its previous name breaks immediately; it is marked
`@internal` and is covered by the `UPGRADE-6.7.md` entry.

Rolling an installation back to a release from before the move requires clearing the caches. Data serialised after the
move carries the current class names, which the older release cannot load. The forward direction needs nothing:
unserialising a payload written under a previous name resolves through the alias.

Code that reflects over a previous name, or compares it against `get_class()`, observes the current name.

An installation cannot use `--classmap-authoritative`, because Shopware registers plugin namespaces through
`ClassLoader::addPsr4()` at runtime. The logic-only shims rely on that PSR-4 fallback.

With 6.9 the shim files, the `class_alias()` calls in the surviving classes and the service id aliases are deleted
together with v1, the two routes move, and the previous names stop resolving.
