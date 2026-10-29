---
title: Telemetry Label Extensibility
date: 2026-10-19
area: framework
tags: [core, telemetry, metrics, extensibility]
---

## Context

Telemetry v2 ([ADR 2026-04-23](./2026-04-23-telemetry-v2-metrics-evolution.md)) bounds metric label values in two ways, and neither is open to extensions:

- Allow lists (`allowed_values` in yaml) are declared with `performNoDeepMerging`, so any later config source replaces the list.
  A plugin that ships its own list overwrites core's, two plugins overwrite each other; only project config can widen a list safely.
- Framework internal labels, like `entity_group` or `domain`, are produced by internal resolvers with hardcoded maps.
  Everything an extension adds (entities, routes, messages, etc.) folds into `other`.

Extensions need a way to surface their values in core metrics, and operators need a way to control the resulting label taxonomy.

## Decision

### 1. Allow lists become additive via `add_allowed_values`

A label declares its values through two sibling keys with different merge semantics:

- `add_allowed_values` contributes values: later sources append (native Symfony list merge).
- `allowed_values` declares the complete, final list: it keeps `performNoDeepMerging`, the last source to set it wins.
  When present, every `add_allowed_values` contribution and every app contribution is ignored - setting `allowed_values` is both replace and lock.

Core ships extensible labels (like `payment_method`) with `add_allowed_values`, structurally closed labels (`result`, `status_class`, ...) keep `allowed_values` and stay locked.

The key processing is done at container build: a present `allowed_values` wins and `add_allowed_values` is cleared, otherwise `add_allowed_values` becomes the deduplicated union.
A label combining values with `policy: open`, or declaring neither, fails the container build.

Locking of open labels is an operator-level decision, so plugins meant for public distribution should not do it (risk of conflicts with other plugins).
But this is not enforced - operator's project policy can still be configured through a plugin.

A value outside the effective list is replaced with `replace_unknown_label_values_with` (default `other`) silently, in every environment.
The previous dev/test replacement notice is removed: an extension that delivers custom route/entity/task should not be forced to provide telemetry configuration (opt-in approach), and should not produce logs noise at the same time.
This supersedes the "log replacements at notice level in dev/test" decision of the v2 ADR; typo detection moves to emitter unit tests and to `other` showing up in dashboards where it should not.

To collapse a label entirely, lock it to exactly the replacement value: `allowed_values: [other]`.

### 2. Named mappings for derived labels

A mapping is a named table `key → label value` under `shopware.telemetry.metrics.mappings` config key.
A label served by a mapping is declared with `policy: open`; the serving resolver owns the binding name as a class constant and bounds the cardinality.
There is deliberately no label-level `mapping` reference: the `key` for mapping is extracted by label resolver and mapping reference should stay at the same place.

- A key is exact, or a prefix when it ends with `*`. Exact beats prefix, the longest prefix wins.
- All keys of one mapping should come from a single identifier space (route names, entity names, event names, ...); resolvers that extract differently-shaped keys should consult different mappings.
- Resolution order: code based rules are kept in the resolver (`is_a` checks, route scopes, core pass-through sets).
  Entries from configuration win so an extension or operator can always correct a heuristic.
- Merge is the standard deep merge: a later source overrides the same key, so project config overrides core and plugin entries.
  Extension-defined mapping names must carry a vendor prefix (`swag_paypal_refund_kind`); bare names are reserved for core.
- The core maps move out of the resolver constants into the shipped `telemetry.yaml` where possible, so the known values are visible in one place.
  Only rules not expressible as `key → value` stay in PHP.

Example: `task_name` uses a mapping rather than an allow list: scheduled tasks are a growing, mostly-noise list, and a mapping gives operators possibility to extract only interesting values, including those that will come with plugin updated (prefix-based approach).

### 3. The names become contract

At feature stabilization, the following join the BC promise and change only in majors, announced in the upgrade guide:

- metric names and label names,
- mapping names and the identifier space each mapping is keyed on,
- the merge semantics of `add_allowed_values`, `allowed_values` and `mappings`,
- the per-label mechanism (allow list or mapping).
  Switching an allow-list label to a mapping fails the container build for every plugin or project that contributed values to it;
  the reverse silently orphans mapping entries.

The shipped group values (the right-hand side of the mapping tables and the core `add_allowed_values`) are not part of the promise.
Core may regroup, rename or add values in minors.

### 4. Apps contribute via `Resources/config/telemetry.xml`

Apps ship a dedicated `telemetry.xml` with `label-values` (allow-list contributions) and `mapping` (mapping entries) elements; there is no inline `manifest.xml` section.
Accepted entries are persisted through the app feature system (one `app_feature` row of type `telemetry` per app) at install and update — no dedicated storage — and unioned into the compiled config at runtime once per process.
The runtime read is lazy and fail-soft: on a storage failure the loader logs a warning once and the process keeps the compiled config — telemetry must never add a failure mode to the paths it observes.

App entries add, never override: locked labels ignore app values, and app mapping entries are consulted only after declared config and the resolver's code-level rules, so an app can label its own identifiers but never relabel a series core already produces.
The operator additionally gates the whole channel with `accept_app_contributions`: `all` (default), `no-new-values`, or `none`.
The middle policy is one per-entry predicate — an entry is accepted iff it does not introduce a label value absent from the operator-declared config — so apps may always group into the operator's taxonomy (zero cardinality impact), while minting new values is the operator's call.
This is the fleet-level guarantee for multi-tenant operators whose customers upload arbitrary apps.
The reference set of a mapping is the values present in its configured table: never other apps' contributions, and not values that only a resolver's code-level rule produces.
Contributions are documented as suggestions: an app cannot observe the shop's metrics, so no app-visible behavior differs between installations that accept or fold them.
Install validation is lenient — an unknown metric, unknown label, locked label, or colliding key is skipped and logged once as a warning, never failing the install.
A file that does not parse or violates the schema fails the install, like the other app configuration files.
There is no `app:validate` check: the install-time warning is the feedback channel.
Entries dropped at runtime (platform upgraded underneath an installed app, late collisions) are logged at debug level.
The merged app config is memoized per process without a per-request reset, so long-running processes (workers) pick up changed app contributions on restart.

## Consequences

- The public surface stays declarative: yaml for plugins and projects, no PHP extension points.
- Plugins and projects widen allow lists with `add_allowed_values` and contribute mapping entries in yaml; apps use `telemetry.xml`.
  Extensions that do not opt in keep folding into `other`, silently.
- Operators close an allow list with `allowed_values` (which also locks it against further contributions) and reshape mapped labels per key or prefix in project config.
  `debug:config shopware` shows the effective state.
- What folded into `other` is not reconstructable from the emitted series.
  If the need arises, an opt-in debug flag that logs replacements can be added without breaks.
- Developers must choose a label's mechanism consciously: allow lists for sets where the raw value is desired and subtraction is rare, mappings for open, growing populations where folding into limited list of values is required.
- Operators may set `accept_app_contributions: no-new-values` (or `none`) in project config; label space is then bounded by operator-controlled config regardless of installed apps.
  Collector-level cardinality guards (relabeling, processors) remain the infrastructure backstop.
- Resolvers keep their signatures and `@internal` status; their job shrinks to key extraction, registry lookup, and code-level fallback.

## Considered alternatives

1. PHP attribute or interface for declaring groups on classes.
   Rejected: a declarative table is cacheable and introspectable, and configuration stays the single public surface. If apps support is needed - it can't be implemented using attributes.
2. Keep the dev/test replacement notice (conditionally for closed labels only).
   Rejected: additional complexity, hard to distinguish cases where extension just did not opt-in vs `accidental` typo cases.
3. `task_name` as an allow list with plugin opt-in.
   Rejected: gives operators no subtractive tool except maintaining the full list, which every plugin update can extend.
