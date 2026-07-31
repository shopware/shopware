# Stabilize a feature flag in Administration tests

This codemod removes a stabilized feature flag from `it.activeFeatureFlags()` calls. It converts the call to a regular
`it()` when no experimental feature flags remain.

Run it from the Administration directory. The target directory defaults to `src`:

```bash
npm run codemod:stabilize-feature-flag -- FEATURE_FLAG [target-directory]
```
