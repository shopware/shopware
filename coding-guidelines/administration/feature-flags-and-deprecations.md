# Administration feature flags and deprecations

Follow the general feature-flag rules in [core feature flags](../core/feature-flags.md). These Administration-specific rules cover JavaScript, Vue, and Admin extension APIs.

## Feature flags

- Use feature flags for temporary rollout or deprecation branches, not as permanent configuration.
- Use uppercase flag names for Administration JavaScript flags, for example `V6_8_0_0` or `ADMIN_COMPOSITION_API_EXTENSION_SYSTEM`.
- Keep each flag focused on one behavior or migration path.
- Avoid nested feature flags; they create hard-to-test state combinations.
- Keep the old behavior inside the branch that will be deleted when the flag is removed.
- Test both relevant flag states when both branches are still supported.

## Deprecations

- Mark deprecated Administration APIs with `@deprecated tag:vX.Y.Z - ...` and name the replacement.
- Guard a deprecated public API at the boundary where it is consumed, so legacy use warns before the major and throws once the major flag is active. `sw-deprecation-rules/require-deprecation-guard` enforces this.
- Document the migration path when deprecating public Administration extension points.
- Do not introduce new internal callers of deprecated APIs; move core/Admin code to the replacement.
- When removing a flag, remove the legacy branch, flag configuration, obsolete tests, and stale documentation in the same change.

### Runtime guards

For a deprecated global API, service, exported function, or component `methods` / `computed` member, call the guard as the first statement of the member:

```ts
Shopware.Feature.triggerDeprecationOrThrow(
    'V6_8_0_0',
    'sw-select-base.computePath() is deprecated. Use `Element.contains()` instead.',
);
```

For a deprecated component or prop, annotate it instead. The deprecation plugin guards a component when it is created and a prop when it is supplied:

```js
export default {
    deprecated: { version: 'v6.8.0.0', comment: 'Use "mt-select" instead.' },

    props: {
        emptyImagePath: {
            type: String,
            required: false,
            deprecated: { version: 'v6.8.0.0', comment: 'Use "emptyIcon" instead.' },
        },
    },
};
```

`@private` on the declaration itself and identifiers starting with `_` take precedence over `@deprecated`: they are not public contracts and need no guard.

Types, styles, tests, Twig markup, `data`, store `state` and `getters`, `watch`, `provide` and `inject` stay static-only. Anything else that cannot carry a guard has to record why, on the same annotation:

```ts
/**
 * @deprecated tag:v6.8.0 - Will be removed
 * @deprecationGuard static-only - Module-level import, there is no runtime use boundary.
 */
```

See [ADR: Administration JavaScript deprecation guards](../../adr/2026-08-10-administration-javascript-deprecation-guards.md).
