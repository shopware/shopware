---
title: Telemetry v2 — Metrics Evolution
date: 2026-04-23
area: framework
tags: [core, telemetry, metrics, monitoring, observability, prometheus, opentelemetry]
---

## Context

The initial telemetry abstraction layer ([ADR 2024-07-30](./2024-07-30-add-telemetry-abstraction-layer.md)) introduced a unified
metrics API behind the `TELEMETRY_METRICS` experimental feature flag. After running the v1 implementation for some time,
several issues emerged that need to be addressed before stabilization in v6.8:

- Six configuration keys (`namespace`, `enabled`, `allow_unknown_labels`, `allow_unknown_label_values`,
  `enable_internal_metrics`, `replace_unknown_label_values_with`) are declared in `Configuration.php` but never
  read by any PHP code — creating a false API surface.
- The `TELEMETRY_METRICS` feature flag is the only runtime kill-switch. Once removed, there is no way to globally 
  disable metrics.
- Telemetry event subscribers are registered and invoked even when telemetry is disabled, violating the
  zero-overhead goal. Expensive subscribers like `EntityTelemetrySubscriber` (recursive criteria tree walk)
  and `MessageQueueTelemetrySubscriber` (message serialization) run on every event.
- The label allowlist silently strips unknown labels/values with no feedback in dev/test, masking developer mistakes.
  It also cannot support open label sets (HTTP status codes, entity names) common in real metrics.
- There is no `flush()` lifecycle hook, moving metrics emitting workflow control to the transports. 
- There is no mechanism for collecting metrics that require expensive computation (e.g. database aggregations like
  order count made during the day).

Besides this ADR is a chance to reiterate some of the decisions made in v1.

## Decision

### 1. Keep `emit(ConfiguredMetric)` as the Meter API

The existing single-method Meter API is preserved. The `Meter::emit(ConfiguredMetric)` pattern is intentional:
- Small interface: One method, one extension point. Transports implement one thing.
- Lazy evaluation: The closure in `ConfiguredMetric.value` defers expensive computation until the Meter
  confirms the metric is enabled. The caller says "here's how to compute this value", the Meter decides
  whether to compute it. This keeps the enabled-check centralized in the abstraction rather than scattered
  across emitters.
- Central config lookup: All metrics go through the same path — config check, enabled check, label filtering.
- Operator control: Enable/disable per metric in YAML without code changes.

The trade-off is that call sites are less self-documenting — developer can't tell without checking config whether
an emission is a counter or histogram.

**Considered alternative — typed Meter methods (`counter()`, `gauge()`, `histogram()`, `upDownCounter()`):**
  - Self-documenting call sites.
  - Can enforce value constraints (counters reject negative values).
  - But increases the interface surface (4+ methods instead of 1). Every new metric type = new method
    on the interface and every transport.
  - Config lookup is still needed for enabled check, label filtering, description, unit — so the typed
    method just skips type resolution.
  - Closures support for lazy evaluation becomes repetitive: each method would need `int|float|\Closure` for the
    value parameter.

**Considered alternative — static factory methods on `ConfiguredMetric` (`ConfiguredMetric::counter(...)`):**
  - Better readability at the call site.
  - But static calls are harder to test.
  - The benefit is marginal given the type is declared in YAML config.

### 2. Add `flush()` to `MetricTransportInterface`

```php
interface MetricTransportInterface
{
    public function emit(Metric $metric): void;
    public function flush(): void;
}
```

An internal `TelemetryFlushListener` listens on `kernel.terminate` and `console.terminate`
to call `flush()` on all transports. These Symfony events fire once for the main
request (not for sub-requests like inline ESI) and once at the end of CLI commands respectively.
`flush()` is not exposed on the `Meter` — it is a lifecycle concern managed by the framework, not
something emitters should call.

Push transports can batch emissions and send on flush. Pull transports can write aggregated values to
storage. Transports that don't need lifecycle management implement `flush()` as a no-op.

For long-running workers (Symfony Messenger), `kernel.terminate` and `console.terminate` do not fire
per message — flush happens when the worker process exits. If the need emerges to flush worker metrics
more often, it can be implemented in the listener (e.g. listening on worker events), as transports
already support `flush()`. This is outside the current scope.

**Considered alternative — no `flush()`, transports manage their own lifecycle:**
  - OTel SDK manages its own flush via `register_shutdown_function` internally.
  - But the framework cannot guarantee emission order. A transport's own flush
    might fire before all metrics are emitted by other listeners.
  - Future transports would need to register their own shutdown hooks, duplicating the pattern.
  - Adding the method after stabilization would be a BC break (or separate interface).

**Considered alternative — `register_shutdown_function` instead of Symfony events:**
  - Universal: fires for HTTP, CLI, and on process exit regardless of Symfony lifecycle.
  - Used by OTel PHP SDK as its primary flush mechanism.
  - But less controlled: runs after Symfony lifecycle, order depends on registration sequence.
  - `kernel.terminate` and `console.terminate` integrate natively with Symfony, support event
    priority, and are testable.

**Considered alternative — flush per worker message (`WorkerMessageHandledEvent`):**
  - Ensures metrics are visible promptly in long-running workers.
  - But excessive: each flush is a potential network call for push transports. Workers processing
    100+ messages/second would generate 100+ flush calls. OTel PHP SDK does not implement
    per-message flushing either — it relies on process-exit shutdown.
  - If needed, transport-level activity-based batching is more appropriate.

### 3. Keep `MetricTransportFactoryInterface`

The factory pattern is preserved. It serves a legitimate purpose: transports like OpenTelemetry require metric
instruments (especially histograms with custom bucket boundaries from `parameters`) to be pre-configured at
construction time, before any `emit()` call. The factory receives the full `TransportConfig` (all metric definitions)
at construction, enabling this pre-configuration stage.

**Considered alternative — remove factory, tag transport services directly:**
  - Simpler: one less interface.
  - But the transport still needs access to the full metric catalog at construction time for
    pre-configuration. This would require injecting `TransportConfig` via DI constructor, which is
    equivalent to the factory but less explicit about the "create and configure" lifecycle stage.

### 4. Three-level computation savings for metrics

Three mechanisms work together to minimize overhead at different granularities:

Level 1 — Global off: When `shopware.telemetry.metrics.enabled` is `false`:
  - `Meter::emit()` checks the `enabled` flag and returns immediately. This is already near-no-op.
  - A compiler pass removes services tagged with `shopware.telemetry.subscriber` (event subscribers,
    flush listener) and `shopware.telemetry.slow_metric_collector` from the container, so they are
    neither invoked by the event dispatcher nor iterated by the slow-metric task handler.
  - The `CollectSlowMetricsTask` itself stays registered but reports `shouldRun() === false`, so the
    scheduler keeps the row in `skipped` state without dispatching.
  - Inline emitters (e.g. `RetryableQuery` via `MeterProvider`) still call `Meter::emit()`, which
    short-circuits on the `enabled` check.
  - Result: no subscriber invocation, no metric processing. The only remaining cost is the `emit()`
    call itself with an early return — negligible.

Level 2 — Per-metric off: When a specific metric's `enabled` is `false` in YAML:
  - `Meter::process()` checks `metricConfig.enabled` after config lookup, returns null.
  - The subscriber still fires, but the closure (if used) is never invoked, `Metric` is never
    constructed, and no transport receives the emission.

Level 3 — Expensive value: When the metric is enabled but computation is costly:
  - The emitter wraps the expensive part in a closure: `new ConfiguredMetric(name: '...', value: fn () => expensiveQuery())`.
  - The Meter only calls the closure if the metric is enabled (after Level 2 check).
  - This keeps the enabled-check centralized in the Meter rather than requiring each emitter to
    check `isEnabled()` before computing.

The compiler pass only affects subscribers; the `enabled` check in `Meter::emit()` covers inline emitters.

**Subscriber identification**: Telemetry subscribers are identified by the `shopware.telemetry.subscriber`
DI tag, added to their service definitions. Slow-metric collectors are identified by the
`shopware.telemetry.slow_metric_collector` tag. When telemetry is globally disabled, the compiler pass
removes the entire service definition for both tag groups.

**Considered alternative — marker interface (`TelemetryAwareSubscriberInterface`):**
  - Type-safe identification, discoverable via IDE.
  - But creates an interface solely for DI purposes — no methods, no behavior.
  - DI tag is the established pattern for this kind of concern.

**Considered alternative — conditional DI file loading:**
  - Clean: subscribers simply don't exist in the container when disabled.
  - But telemetry subscribers are scattered across domain-specific DI files (`cache.xml`, `app.xml`,
    `plugin.xml`, etc.), where they belong to.
  - Doesn't extend to plugins — they can't use this mechanism for their own subscribers.

### 5. Label validation with type-aware policies

Replace the current silent label stripping with explicit, configurable validation.

#### Unknown label names

A label name not declared in the metric's YAML definition: log at error level in prod, throw
`MissingMetricConfigurationException` in dev/test. Consistent with the existing behavior for
unknown metric names.

**Considered alternative — `allow_unknown_labels` global flag:**
  - Would let plugins attach ad-hoc labels to framework metrics without editing definitions.
  - But undermines the principle that YAML is the source of truth for metric shape.
  - If a plugin needs a label, it may extend the definition via Symfony config merge.
  - Two code paths to maintain and test (strict vs permissive).

#### Unknown label values

When a label defines `allowed_values` and an emitted value is not in the list, the behavior depends
on the metric type. The default policy is derived from the metric type's semantics:

| Metric type | Default policy | Reasoning |
|------------|---------------|-----------|
| Counter | `replace` | Additive: losing an increment makes the total wrong. Replace preserves the count. |
| Histogram | `replace` | Additive: losing an observation skews distributions. Replace preserves observation count. |
| UpDownCounter | `replace` | Additive: losing a delta causes value drift. |
| Gauge | `discard` | Non-additive: `replace` causes meaningless oscillation when multiple sources collapse into one label value (last write wins for the same label set). |

- Replacement value: Configured globally via `shopware.telemetry.metrics.replace_unknown_label_values_with`
  (default: `'other'`). Applies to all labels using the `replace` policy.
- Per-label policy override: Each label can set `policy: replace|discard|open` to override the type default.
  The developer who ships the metric knows the expected values and sets the appropriate policy. Operators can override
  per-metric via Symfony config merge.
- Allowed policies: `replace` is allowed for additive types, `discard` for non-additive, `open` for all.
- No global default policy config: The type-based defaults are sufficient. Per-metric definition is the right level
  for overrides.

#### Mandatory explicit label configuration

Every label in a metric definition **must** have either `allowed_values` or `policy: open`. Symfony
configuration validation rejects definitions where a label has neither — the container will not build.
This prevents accidentally creating unbounded-cardinality labels by omitting `allowed_values`.

| Label config | Meaning |
|-------------|---------|
| `allowed_values: [...]` (no `policy`) | Values restricted. Unknown value policy = type default (replace for additive, discard for gauge). |
| `allowed_values: [...]` + `policy: discard` | Values restricted. Unknown values → drop measurement. |
| `allowed_values: [...]` + `policy: replace` | Values restricted. Unknown values → replacement string. |
| `policy: open` (no `allowed_values`) | All values pass through. Explicit developer opt-in for unbounded label sets. |
| `allowed_values: [...]` + `policy: open` | **Validation error** — contradictory. |
| Neither `allowed_values` nor `policy` | **Validation error** — developer must make a conscious choice. |

**Considered alternative — implicit open when `allowed_values` is absent:**
  - Less config friction for open labels (no `policy: open` line needed).
  - But a developer who forgets `allowed_values` accidentally creates an open label. There is no way
    to distinguish intentional open from accidental omission.
  - Requiring explicit `policy: open` is one line of YAML — minimal overhead for preventing
    accidental cardinality explosions.

**Considered alternative — global `allow_unknown_label_values` flag, originally added to config (permissive mode):**
  - Quick toggle to disable all value filtering.
  - But redundant: if you don't want value restrictions, use `policy: open` per label.
  - Creates confusing precedence with per-label policies.

**Considered alternative — cardinality limits (`max_cardinality: 50` per label):**
  - Framework-level protection against unbounded label sets.
  - But requires per-request state tracking across PHP's shared-nothing processes (APCu or similar).
  - Reimplements what OTel Collector's processors do, but worse.
  - Cardinality control for open label sets belongs at the infrastructure level.

Example configuration:

```yaml
definitions:
    http.requests.count:
        type: counter
        labels:
            method:
                allowed_values: ['GET', 'POST', 'PUT', 'DELETE']
                # policy defaults to 'replace' (counter is additive)
            status_code:
                policy: open   # explicit opt-in for unbounded values

    active.sessions:
        type: gauge
        labels:
            region:
                allowed_values: ['eu', 'us', 'asia']
                # policy defaults to 'discard' (gauge is non-additive)
```

### 6. Metric storage is a transport concern

Core provides no metric storage abstraction (no `MetricStorageInterface`, no APCu/Redis adapters).
Push transports forward metrics to a collector. Pull transports persist values across PHP requests
using their own storage (e.g. `promphp/prometheus_client_php` with Redis or APCu backends).

**Considered alternative — core provides `MetricStorageInterface` with APCu/Redis implementations:**
  - Would let multiple pull transports share storage logic.
  - But APCu is not available in all environments.
  - `promphp/prometheus_client_php` already provides mature APCu and Redis adapters.
  - Adding storage to the framework violates goal to have minimal abstraction.
  - Only useful for pull transports — push transports don't need it.

### 7. Scrape-time events are not part of core

Pull transports own their scrape lifecycle, endpoint, and any scrape-time events internally.

**Considered alternative — core dispatches a `MetricCollectionEvent`:**
  - Useful for Prometheus pull: at scrape time, subscribers provide current values.
  - But for push transports (OTel), there is no natural trigger point. It doesn't belong at
    `kernel.terminate` (every request, wasteful for static info) or at task time (redundant with
    `SlowMetricCollectorInterface`).
  - Placing a pull-transport-specific event in core creates a confusing API.

### 8. Slow metric collection via scheduled task

Metrics requiring expensive computation are collected by a Shopware scheduled task iterating tagged
`SlowMetricCollectorInterface` services:

```php
interface SlowMetricCollectorInterface
{
    /** @return iterable<ConfiguredMetric> */
    public function collect(): iterable;
}
```

The task runs at a configurable interval (`shopware.telemetry.collection_interval`, default 60 seconds).
Each collector is wrapped in try/catch — one failure does not prevent others from running.
Collected metrics are emitted via `Meter`, so both push and pull transports receive them through the
standard `emit()` path.

Plugins needing a different frequency register their own Shopware scheduled task.

**Considered alternative — per-collector configurable interval:**
  - Fine-grained control over collection frequency.
  - But requires last-run timestamp tracking per collector, interval subdivision logic, persistence between 
    task invocations.

**Considered alternative — event-based collection:**
  - Task dispatches a `CollectSlowMetricsEvent`, subscribers add metrics.
  - But error isolation is worse: one subscriber throwing stops event propagation (relying on developers discipline
    to properly process errors).
  - Return-based contract (`iterable<ConfiguredMetric>`) is cleaner than event mutation.

**Considered alternative — configurable trigger (task vs transport at scrape time):**
  - Fresh data at scrape time for pull transports.
  - But slow collectors risk Prometheus scrape timeouts.
  - The scheduled task gives predictable load and timeout-safe scrapes at the cost of staleness
    up to the collection interval — acceptable for business or information metrics.

### 9. Keep `MeterProvider` static accessor

`MeterProvider` (static service locator, bound at `Framework::boot()`) is required by `RetryableQuery`
and `RetryableTransaction` (static code that cannot receive DI). Until those callers are refactored,
`MeterProvider` is the only way to instrument them.

### 10. Clean up configuration

| Key | Action |
|-----|--------|
| `enabled` | Wire to `Meter::emit()` early return + compiler pass |
| `namespace` | Wire to `TransportConfig`, passed to transport factories |
| `replace_unknown_label_values_with` | Wire to label value replacement logic (default: `'other'`) |
| `allow_unknown_labels` | Remove — superseded by per-label name validation |
| `allow_unknown_label_values` | Remove — superseded by per-label value policies |
| `enable_internal_metrics` | Remove — per-metric `enabled` is sufficient |

## Consequences

- **Transport packages** (shopware/opentelemetry, shopware/prometheus-exporter):
  - Must implement `flush()` on `MetricTransportInterface` (no-op if not needed).
  - Prometheus transport owns its storage, scrape endpoint, and any scrape-time events.
- **Developers**:
  - Telemetry subscribers should be tagged with `shopware.telemetry.subscriber` for zero-overhead global disable.
  - For expensive metrics: use closure values in `ConfiguredMetric` for lazy evaluation.
  - For slow/periodic metrics: implement `SlowMetricCollectorInterface`, tag the service.
  - Every label must have either `allowed_values` or `policy: open` — config validation enforces this.
    Unknown label names throw in dev/test — typos caught earlier.
  - `emit(ConfiguredMetric)` remains the API. No change to existing emitter code.
- **Operators**:
  - Use `shopware.telemetry.metrics.enabled: true/false` to enable/disable globally
    (additional kill-switch alongside `TELEMETRY_METRICS` feature flag, which will be removed with feature stabilization).
  - Tune `shopware.telemetry.collection_interval` for slow metric freshness vs. load.
  - Override per-label `policy` or `allowed_values` via config merge.
  - Cardinality control for open label sets MUST be done at infrastructure level (OTel Collector processors, Prometheus relabeling).
- **Migration**:
  - `flush()` added to `MetricTransportInterface` — transport packages must implement it.
  - Unused config keys should be marked for removal in next major.
- **Trade-offs**:
  - `emit(ConfiguredMetric)` is less self-documenting than typed methods. The metric type is not
    visible at the call site — only in the YAML config. This is a readability trade-off for a
    smaller, more flexible interface.
  - Subscriber registration cannot be toggled at runtime without a container rebuild, so this is a deployment-time decision.
  - The scheduled task for slow metrics introduces data staleness up to the collection interval, which should be
    acceptable for the type of metrics it serves (business aggregations, info metrics).
  - `MeterProvider` remains a static service locator. Necessary until static callers are refactored.
