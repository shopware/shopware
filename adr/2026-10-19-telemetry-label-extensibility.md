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

At feature stabilization, metric names, label names, mapping names, the shipped group values, and the per-label mechanism (allow list vs mapping) join the BC promise: rename, removal, or mechanism switch only in majors, announced in the upgrade guide.
Changing an allow-list label to a mapping breaks plugin config (contributed values combine with the now-open label and fail the build); the reverse silently orphans mapping entries.

## Consequences

- The public surface stays declarative: yaml for plugins and projects, no PHP extension points.
- Plugins and projects widen allow lists with `add_allowed_values` and contribute mapping entries in yaml.
  Extensions that do not opt in keep folding into `other`, silently.
- Operators close an allow list with `allowed_values` (which also locks it against further contributions) and reshape mapped labels per key or prefix in project config.
  `debug:config shopware` shows the effective state.
- What folded into `other` is not reconstructable from the emitted series.
  If the need arises, an opt-in debug flag that logs replacements can be added without breaks.
- Developers must choose a label's mechanism consciously: allow lists for sets where the raw value is desired and subtraction is rare, mappings for open, growing populations where folding into limited list of values is required.
- Resolvers keep their signatures and `@internal` status; their job shrinks to key extraction, registry lookup, and structural fallback.

## Considered alternatives

1. PHP attribute or interface for declaring groups on classes.
   Rejected: a declarative table is cacheable and introspectable, and configuration stays the single public surface. If apps support is needed - it can't be implemented using attributes.
2. Keep the dev/test replacement notice (conditionally for closed labels only).
   Rejected: additional complexity, hard to distinguish cases where extension just did not opt-in vs `accidental` typo cases.
3. `task_name` as an allow list with plugin opt-in.
   Rejected: gives operators no subtractive tool except maintaining the full list, which every plugin update can extend.
