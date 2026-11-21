# Administration feature flags and deprecations

Follow the general feature-flag rules in [core feature flags](../core/feature-flags.md). These Administration-specific rules cover JavaScript, Vue, and Admin extension APIs.

## Feature flags

- Use feature flags for temporary rollout or deprecation branches, not as permanent configuration.
- Use uppercase flag names for Administration JavaScript flags, for example `V6_8_0_0` or `ADMIN_COMPOSITION_API_EXTENSION_SYSTEM`.
- Keep each flag focused on one behavior or migration path.
- Avoid nested feature flags; they create hard-to-test state combinations.
- Keep the old behavior inside the branch that will be deleted when the flag is removed.
- Test both relevant flag states when both branches are still supported.

## Template changes

Twig blocks, slot content, rendered components, CSS classes and `$refs` of a public component template are extension points.
Keeping the block names is not enough:
replacing the component inside a block, removing slot content or renaming classes breaks extensions that override, style or reach into it.
Search for precedents before changing such markup, for example with `grep -rn "feature.isActive('v6.8.0.0')" --include=*.twig src/Administration`.

Put such a change behind the major flag and keep the old markup in the branch that is removed at the major:

```twig
<template v-if="feature.isActive('v6.8.0.0')">
    {% block sw_example_entity_select %}
    <sw-entity-single-select ... />
    {% endblock %}
</template>

<template v-else>
    {% block sw_example_select %}
    <sw-single-select ... />
    {% endblock %}
</template>
```

- Give the new markup new blocks, because a template cannot define a block twice; keep the old blocks unchanged in the `v-else` branch.
- Inject `feature` in the component, the template reads it.
- Keep the old JavaScript path running while the flag is inactive, for example the loading in `created` or a watcher, behind `!this.feature.isActive('V6_8_0_0')`.
- Deprecate the members that only the old path needs.
  Core calls them from the old path on purpose, so wrap these calls in `Shopware.Feature.silence('V6_8_0_0', () => ...)`.
  The callback must be synchronous, so also silence deprecated calls inside a `.then()` of the old path.
- A deprecated member that the old markup calls directly, like a `@search` handler, runs on every use before the major.
  It has no guard and states why, see [Runtime guards](#runtime-guards).
- Test the new markup with `it.activeFeatureFlags(['v6.8.0.0'])` and the old one with `it.deprecated('v6.8.0.0')`, see the [Jest helper ADR](../../adr/2026-08-06-administration-jest-feature-flag-helpers.md).
  Tests of the old markup can live in a separate `<component>.legacy.spec.js` next to the main spec.
- Document the new blocks and the switch in the release notes and the removed markup in the upgrade notes.

## Deprecations

- Mark deprecated Administration APIs with `@deprecated tag:vX.Y.Z - ...` and name the replacement.
- Guard a deprecated public API at runtime where it is consumed, see [Runtime guards](#runtime-guards). `sw-deprecation-rules/require-deprecation-guard` enforces this, and that every runtime guard has a `@deprecated tag:` marker.
- Document the migration path when deprecating public Administration extension points.
- Do not introduce new internal callers of deprecated APIs; move core/Admin code to the replacement.
- At the major, remove the flag checks, the legacy branch, obsolete tests, and stale documentation in the same change.
  The major flag itself stays registered in `feature.yaml` (default on, not toggleable), because plugins use it as a version switch.
  Every other flag is removed from `feature.yaml` once it is enabled.

### Runtime guards

Call `Shopware.Feature.triggerDeprecationOrThrow()` first thing in a deprecated global API, service method, exported function, or component `methods` / `computed` member. It warns in development builds before the major and throws once the major flag is active:

```ts
Shopware.Feature.triggerDeprecationOrThrow('V6_8_0_0', 'sw-example.oldMethod() is deprecated. Use newMethod() instead.');
```

Annotate a deprecated component or prop with the `deprecated` option instead. The deprecation plugin guards the component every time it is created, and the prop every time a parent supplies it:

```js
Component.register('sw-example', {
    deprecated: { version: 'v6.8.0.0', comment: 'Use "mt-example" instead.' },

    props: {
        emptyImagePath: {
            type: String,
            required: false,
            deprecated: { version: 'v6.8.0.0', comment: 'Use "emptyIcon" instead.' },
        },
    },
});
```

Pass a registered major flag and a version like `v6.8.0.0`. Anything else could never throw, so it logs a console error instead.

The guard is the first statement, so no early return or condition skips it.

`@private` symbols and identifiers starting with `_` are no public contract and need no guard.
Neither do values, types, lifecycle hooks, `data`, `watch`, `provide`, `inject`, store `state` and `getters`, which core runs for every instance or have no use to guard.
Any other deprecated function that core itself runs on every use states why it has no guard:

```ts
// eslint-disable-next-line sw-deprecation-rules/require-deprecation-guard -- the boot sequence runs it until Vuex is removed
export default function initState() {}
```

See the [ADR](../../adr/2026-08-10-administration-javascript-deprecation-guards.md).

For now, only specs inside the platform's `src/` fail on unasserted deprecations.
In the Jest run of an extension that reuses core's test setup, they are still recorded, so `toHaveTriggered()` works, and majors a test activates still throw.
See the [ADR](../../adr/2026-10-23-fail-on-administration-deprecations-only-in-core-tests.md).

### Deprecation baseline

Jest lets known usages of deprecated APIs pass when they are listed in the deprecation baseline.
In the running Administration, a deprecation whose message is listed in the baseline logs a console error instead of throwing once the major flag is active.
To update it, run both commands:

```bash
DEPRECATION_BASELINE=update composer admin:unit
```

```bash
DEPRECATION_BASELINE=update composer admin:unit:major
```
