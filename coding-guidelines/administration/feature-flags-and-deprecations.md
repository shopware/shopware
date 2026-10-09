# Administration feature flags and deprecations

Follow the general feature-flag rules in [core feature flags](../core/feature-flags.md). These Administration-specific rules cover JavaScript, Vue, and Admin extension APIs.

## Feature flags

- Use feature flags for temporary rollout or deprecation branches, not as permanent configuration.
- Use the flag id as registered in [`feature.yaml`](../../src/Core/Framework/Resources/config/packages/feature.yaml), for example `v6.8.0.0` for a major flag or `JSON_LD_DATA` for a named flag.
  The uppercase env-style spelling `V6_8_0_0` resolves to the same flag, but it is not the convention.
- Keep each flag focused on one behavior or migration path.
- Avoid nested feature flags; they create hard-to-test state combinations.
- Keep the old behavior inside the branch that will be deleted when the flag is removed.
- Test both relevant flag states when both branches are still supported.

## Deprecations

- Mark deprecated Administration APIs with `@deprecated tag:vX.Y.Z - ...` and name the replacement.
- Add a runtime deprecation guard with `Shopware.Feature.triggerDeprecationOrThrow()` to public, runtime-detectable APIs.
  This is mandatory once the helper from [ADR 2026-08-10](../../adr/2026-08-10-administration-javascript-deprecation-guards.md) is merged.
  Until then, document the deprecation in the docblock and in the release notes.
- Document the migration path when deprecating public Administration extension points.
- Do not introduce new internal callers of deprecated APIs; move core/Admin code to the replacement.
- At the major, remove the flag checks, the legacy branch, obsolete tests, and stale documentation in the same change.
  The flag itself stays registered in `feature.yaml` (default on, not toggleable), because flag names are public API.
