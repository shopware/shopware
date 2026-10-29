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
  When present, every `add_allowed_values` contribution is ignored - setting `allowed_values` is both replace and lock.

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

### 4. Apps claim identifiers via `Resources/config/telemetry.xml`

Apps cannot ship yaml, and they cannot observe the shop's metrics either, so they have no stake in how their series are grouped.
What they know and the operator does not is which identifiers (custom entities, flow events, routes, ...) belong to them.
An app therefore claims keys per mapping in a dedicated `telemetry.xml`; it never declares label values.

- A claimed key resolves to a label value derived from the app's technical name (snake case, `SwagBlog` → `swag_blog`).
  Cardinality grows by at most one value per installed app that opts in, never by what an app declares.
- Resolution order per key: configured entry → the resolver's code-level rule → app claim → the replacement value.
  Configured entries and code-level rules win, so an app can label its own identifiers but never relabel a series core already produces;
  declaring a key or a covering prefix in project config also neutralizes an unwanted claim.
- `shopware.telemetry.metrics.accept_app_contributions: false` ignores all claims.
  Fleet operators whose tenants upload arbitrary apps set it in project config; label space is then bounded by operator-controlled config regardless of what tenants install.
- Accepted claims are persisted through the app feature system (one `app_feature` row of type `telemetry` per app) at install and update, no dedicated storage.
  They are read lazily once per process with a fail-soft read: a storage failure is logged once as a warning and the process keeps the compiled config, telemetry must never add a failure mode to the paths it observes.
  There is no per-request reset, so long-running processes (workers) pick up changed claims on restart.
- Install validation is lenient: a key for an unknown mapping, a bare `*`, or a key already declared in config is skipped and logged once as a warning, never failing the install.
  A file that does not parse or violates the schema fails the install, like the other app configuration files.
  A key claimed by more than one app keeps the first app by name, logged at debug level.

Allow lists are not open to apps: widening `payment_method` and friends stays an operator decision in yaml.

## Consequences

- The public surface stays declarative: yaml for plugins and projects, no PHP extension points.
- Plugins and projects widen allow lists with `add_allowed_values` and contribute mapping entries in yaml; apps claim mapping keys in `telemetry.xml`.
  Extensions that do not opt in keep folding into `other`, silently.
- Operators close an allow list with `allowed_values` (which also locks it against further contributions) and reshape mapped labels per key or prefix in project config.
  `debug:config shopware` shows the effective yaml state; app claims live in the `app_feature` table.
- Operators may set `accept_app_contributions: false` in project config; label space is then bounded by operator-controlled config regardless of installed apps.
  Collector-level cardinality guards (relabeling, processors) remain the infrastructure backstop.
- What folded into `other` is not reconstructable from the emitted series.
  If the need arises, an opt-in debug flag that logs replacements can be added without breaks.
- Developers must choose a label's mechanism consciously: allow lists for sets where the raw value is desired and subtraction is rare, mappings for open, growing populations where folding into limited list of values is required.
- Resolvers keep their signatures and `@internal` status; their job shrinks to key extraction, registry lookup, and code-level fallback.

## Considered alternatives

1. PHP attribute or interface for declaring groups on classes.
   Rejected: a declarative table is cacheable and introspectable, and configuration stays the single public surface. If apps support is needed - it can't be implemented using attributes.
2. Keep the dev/test replacement notice (conditionally for closed labels only).
   Rejected: additional complexity, hard to distinguish cases where extension just did not opt-in vs `accidental` typo cases.
3. `task_name` as an allow list with plugin opt-in.
   Rejected: gives operators no subtractive tool except maintaining the full list, which every plugin update can extend.
