---
guide: bc-php
title: Does this PHP change keep its promise to plugin authors?
personas: [architecture, maintainer]
rules:
  - { id: BCPHP-001, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#when-a-break-is-acceptable", origin: "https://github.com/shopware/shopware/pull/20478", fixture: "tests/guide/bc-php/catch" }
  - { id: BCPHP-002, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#php", origin: "https://github.com/shopware/shopware/pull/18599", fixture: "" }
  - { id: BCPHP-003, since: 2026-10-09, source: "coding-guidelines/core/feature-flags.md#planning-public-api-changes", origin: "https://github.com/shopware/shopware/pull/20978", fixture: "tests/guide/bc-php/ignore" }
  - { id: BCPHP-004, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#deprecation-and-announcement", origin: "coding-guidelines/core/feature-flags.md#announcing-a-deprecation-before-its-replacement-is-stable", fixture: "" }
  - { id: BCPHP-005, since: 2026-10-09, source: ".agents/skills/shopware-php-code/SKILL.md#deprecations", origin: "https://github.com/shopware/shopware/pull/20927", fixture: "" }
  - { id: BCPHP-006, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#documenting-a-break", origin: "https://github.com/shopware/shopware/pull/20934", fixture: "tests/guide/bc-php/catch" }
  - { id: BCPHP-007, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#when-a-break-is-acceptable", origin: "https://github.com/shopware/shopware/pull/11516", fixture: "" }
  - { id: BCPHP-008, since: 2026-10-09, source: ".agents/skills/shopware-php-code/SKILL.md#structure", origin: "https://github.com/shopware/shopware/pull/21165", fixture: "" }
  - { id: BCPHP-009, since: 2026-10-09, source: "coding-guidelines/core/feature-flags.md#moving-a-class", origin: "https://github.com/shopware/shopware/pull/20525", fixture: "" }
  - { id: BCPHP-010, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#public-api-is-every-extension-surface", origin: "https://github.com/shopware/shopware/pull/15638", fixture: "" }
---

## Why this guide exists

Every non-internal PHP class, method, property, constant, event and service id is a promise to thousands of plugins. The 6.8 stability direction asks for fewer breaks and only justified ones: intentional, announced by a deprecation, with a migration path, and active only in the next major. The base branch decides what is allowed: on a `6.x` base branch nothing may break; on `trunk` a break is legitimate only behind the next major flag or announced with a BC-change attribute. CI catches the mechanical signature breaks; this guide covers the judgement around them.

## Check

- **BCPHP-001** A non-internal public symbol is renamed, removed or changes behaviour, and callers get no path: no old name that forwards with `@deprecated tag:` and `Feature::triggerDeprecationOrThrow()`, no next-major flag branch, no BC-change attribute. A plugin that calls the old name fails with a fatal error after a routine update. The path must cover everything that goes away: when a class drops its parent or becomes readonly, deprecate the inherited methods and setters too (#17101); a renamed public service id keeps a deprecated alias (#16109). Rule: [When a break is acceptable](../../../../coding-guidelines/core/backward-compatibility.md#when-a-break-is-acceptable), [PHP](../../../../coding-guidelines/core/backward-compatibility.md#php). Example: #20478 builds the next-major behaviour now, behind the major flag, and keeps the old behaviour as the default.
- **BCPHP-002** A break or a workaround where an additive change would do: a new constructor parameter without a default on a class plugins instantiate or extend, a new abstract method on an abstract class, a native new parameter (even an optional one) on a public or protected method that subclasses override. Every subclass in the ecosystem must change, while a default value, a non-abstract default method or `func_get_arg()` with `#[NewOptionalParameter]` costs nothing. Rule: [PHP](../../../../coding-guidelines/core/backward-compatibility.md#php). Example: #18599 (add the constructor parameter with a default), #20863 (non-abstract default method instead of an abstract one), #14740 (an optional parameter on a protected method breaks every override).
- **BCPHP-003** A symbol that stays but changes (visibility, a native or PHPDoc type, a new required parameter) is changed directly or marked `@deprecated`. Plugins get no warning, or a warning that says "removed" when it is not. Announce it with the most specific BC-change attribute. Rule: [Planning public API changes](../../../../coding-guidelines/core/feature-flags.md#planning-public-api-changes). Example: #20978, a method that was public by mistake gets `#[VisibilityChange]` now and becomes private in the next major; #15835, a PHPDoc type narrowed to `list<Constraint>` broke the PHPStan run of a downstream extension.
- **BCPHP-004** A deprecation names no concrete replacement (a vague field instead of the replacing class), or points to a replacement that plugins cannot use yet (it is `@internal`, experimental or only active behind a major flag) and warns without `silentUntil:`. Plugin authors see warnings they cannot act on. Rule: [Deprecation and announcement](../../../../coding-guidelines/core/backward-compatibility.md#deprecation-and-announcement), [silentUntil](../../../../coding-guidelines/core/feature-flags.md#announcing-a-deprecation-before-its-replacement-is-stable). Example: the legacy document methods whose v2 replacement only becomes stable with 6.8; #19538, a deprecation that named a field instead of the replacing entity.
- **BCPHP-005** Core still calls a path it deprecates, or a DI `<deprecated>` tag is added while core still references the service id. Every shop then logs deprecations that the merchant cannot act on. Move core callers to the replacement or wrap the call in `Feature::silent()`. Rule: [Deprecations](../../shopware-php-code/SKILL.md#deprecations). Example: #20927 keeps dispatching the deprecated criteria event from core, wrapped in `Feature::silent()`.
- **BCPHP-006** A planned break is documented only in `UPGRADE-6.x.md`, or not at all. Plugin authors read RELEASE_INFO for the current minor and learn about the break only when they upgrade. Ask for a RELEASE_INFO entry with the replacement now and an UPGRADE entry for the break. Rule: [Documenting a break](../../../../coding-guidelines/core/backward-compatibility.md#documenting-a-break). Example: #20934.
- **BCPHP-007** A widely used API changes and nobody checked how extensions use it. Unknown usage is not proof of no impact. Ask for a usage check before the change. Rule: [When a break is acceptable](../../../../coding-guidelines/core/backward-compatibility.md#when-a-break-is-acceptable). Example: #11516, where 597 store plugins called `$context->getToken()`.
- **BCPHP-008** A new public contract (event, public method) hands out an infrastructure object such as a query builder or a connection. The internal query then becomes public API and can never change. Wrap it in an `@internal` decoratable gateway that returns DTOs. Rule: [Structure](../../shopware-php-code/SKILL.md#structure). Example: #21165.
- **BCPHP-009** A class moves by copying, subclassing or renaming instead of `#[ClassMoved]` plus a class alias. Type hints and `instanceof` checks in plugins stop matching. Rule: [Moving a class](../../../../coding-guidelines/core/feature-flags.md#moving-a-class). Example: #20525.
- **BCPHP-010** A new class, interface, method or DTO constructor that is not meant as an extension point is not marked `@internal`, or a new public API whose design may still change is not marked `@experimental`. From its first release it is public API, so every later fix to its shape needs the full deprecation cycle. Rule: [Public API is every extension surface](../../../../coding-guidelines/core/backward-compatibility.md#public-api-is-every-extension-surface). Example: #15638 (an internal DTO), #19429 (generator, cleanup task and struct of one feature), #21152 (a new method with one internal caller on a public class).

## Do not flag

### CI covers

- roave `bc-check` (job `bc-checker` in `.github/workflows/php.yml`): removed public classes and methods, narrowed types, new required constructor parameters on non-internal classes. Do not report the signature break on its own; report what roave cannot see: the missing deprecation path (BCPHP-001), missing release docs (BCPHP-006), or a new `.bc-exclude.php` entry without a reason.
- PHPStan `NoBCPlanningDeprecationRule` (`@deprecated reason:*` instead of a BC-change attribute), `BCChangeAttributeUsageRule` (the attribute describes a real change, uses a valid version and adds the runtime signal), `DeprecatedMethodsThrowDeprecationRule` (deprecated method or class without `Feature::triggerDeprecationOrThrow()`), `FuturePropertyCompatibilityRule` and `FuturePropertyVisibilityChangeRule` (core code that conflicts with an announced property change), `InternalClassRule` and `InternalMethodRule` (test classes, subscribers, compiler passes and service constructors are `@internal`), `AnnotationTagTest` (malformed or stale `@deprecated`, `@experimental` and `silentUntil` tags).

### Legitimate patterns

- On `trunk`: a break behind `Feature::isActive('v6.8.0.0')` or announced with a BC-change attribute, with an UPGRADE entry.
- Changes to `@internal`, `@experimental` or private code, to DI service constructors, and to test code.
- Added classes, constants and events (BCPHP-010 only asks whether they are meant to be public), parameters added through `func_get_arg()` with `#[NewOptionalParameter]`, public methods on `@final` classes, non-abstract methods on decorator base classes.
- Wording and file choice of release notes: the release-docs guide owns them.

## Severity

- `blocking`: a non-internal symbol is removed or changes behaviour with no deprecation path, or anything breaks on a `6.x` base branch. Plugins fail after a routine update.
- `major`, `requires_human: true`: the break is behind the flag but the announcement is missing or unusable (no RELEASE_INFO, no usable replacement), an additive alternative was skipped, or a widely used API changes without a usage check.
- `minor`: core triggers its own deprecation, a class move without `#[ClassMoved]` on a class no plugin is likely to reference, or a new implementation class without `@internal` (BCPHP-010; `major` for a new interface or abstract class plugins will implement).

## Retired

(none yet)
