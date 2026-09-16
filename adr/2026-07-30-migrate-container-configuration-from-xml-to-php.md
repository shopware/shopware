---
title: Migrate container configuration from XML to PHP
date: 2026-07-30
area: framework
tags: [dependency-injection, container, routing, symfony, plugins]
status: accepted
---

## Context

Symfony 7.4 deprecates the XML configuration loaders for service definitions and routes, and Symfony 8 removes them.
At that point the platform defined roughly 2,350 services across 101 XML files plus 12 XML route files, and plugins ship `Resources/config/services.xml` by convention — both block the Symfony 8 upgrade.

YAML remains supported by Symfony, so the target format was a real choice, as was the question of how long to keep XML working for plugins.

## Decision

All platform service and route definitions are migrated to PHP configuration files (`ContainerConfigurator` / `RoutingConfigurator`), and XML configuration support for plugins is deprecated and removed.

- **PHP over YAML.** In PHP config every class reference is a `::class` constant that static analysis, IDE navigation, and refactoring tools understand and verify. YAML would keep the wiring in unchecked strings and merely swap one migration for another.
- **1:1, behavior-neutral migration.** Each XML file became one PHP file with the same basename, loaded at the same position. Equivalence was proven per pull request by diffing `debug:container` and `debug:router` JSON dumps (including hidden services and parameters) before and after, so reviews could focus on style only.
- **Explicit wiring, no autowiring.** The migration's contract was an identical compiled container, which autowiring would have changed. Beyond the migration, core keeps wiring explicit: the compiled container must not silently change with constructor signatures or the set of installed plugins, and explicit definitions keep argument order — which is part of the decoration contract — deliberate and reviewable.
- **Plugin XML configuration is deprecated in 6.7 and removed in 6.8, without a vendored XML loader.** Plugins can already ship `services.php`, `routes.php`, and PHP package config on all supported 6.x versions. Loading XML service, route, or package configuration triggers a deprecation (with bundle and file context) and throws with the 6.8 major feature flag; in 6.8 the XML loaders are dropped and `xml` is removed from `Kernel::CONFIG_EXTS`. Maintaining our own XML loader indefinitely was rejected as long-term maintenance cost for a format Symfony has abandoned.

## Consequences

- The platform contains no XML container or route configuration. New services are registered in the existing `DependencyInjection/*.php` files, following their style.
- Plugin and app-server developers must migrate XML configuration to PHP before 6.8; `UPGRADE-6.8.md` documents the mapping. Until then every XML config load is logged as a deprecation.
- The Symfony 8 upgrade is no longer blocked by configuration format.
