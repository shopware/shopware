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
- Guard a deprecated public API at runtime where it is consumed, see [Runtime guards](#runtime-guards).
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

`@private` symbols and identifiers starting with `_` are no public contract and need no guard. See the [ADR](../../adr/2026-08-10-administration-javascript-deprecation-guards.md).

### Adding a deprecation codemod

Every deprecated component or prop needs a codemod, so extensions can migrate with `eslint --fix` instead of reading the upgrade notes.
Add a migration to the deprecation registry in `src/Administration/Resources/app/administration/src/app/deprecation-registry/definitions`, and add it to the list of its category, for example `definitions/components/index.ts`.
Build it with the helpers from `definitions/helpers`, for example `componentMigration()`, `renameComponent()` and `removeProp()`.

A component replacement splits its steps into two lists:

- `onConvert`: the `renameComponent()` step, then the steps that carry the old component's behavior over.
  They run only on elements the codemod converted.
- `onEveryUsage`: steps that run on every element of the replacement, also on hand-written ones.

```ts
onConvert: [
    renameComponent({ from: 'sw-button', to: 'mt-button' }),
    addPropWhenMissing({ prop: 'variant', value: 'secondary' }),
],
onEveryUsage: [
    replacePropValue({ prop: 'variant', fromValue: 'danger', toValue: 'critical' }),
],
```

Every step helper documents its scenarios: what it fixes, what gets a review comment, and what it leaves for a manual migration.
Steps that read a value from the old usage take a source: `fromSlot()`, `fromProp()` and `firstFoundOf()` for a prop, `fromText()` and `fromAttribute()` for the fields of an array item.

Give every usage step the most automatic fix level that is still correct:

- `auto`: the fix keeps the behavior, for example renaming a component or prop.
- `unsafe-auto`: the fix is usually right but needs a human check, so the codemod marks it with a `TODO Codemod` comment.
- `manual`: no safe rewrite exists, so the usage is only reported with the step's `message` as the hint.

A migration is only as automatic as its least automatic step.

The `sw-deprecation-rules/require-deprecation-codemod` rule reports a `deprecated` option on a component or prop without a matching migration.
If no codemod is possible, disable the rule for that line and give the reason:

```js
// eslint-disable-next-line sw-deprecation-rules/require-deprecation-codemod -- removed without replacement
deprecated: 'v6.8.0.0',
```

To try a codemod on the Administration, run these commands in `src/Administration/Resources/app/administration`:

```bash
# List every migration id with its fix level
npm run deprecation:migrate -- --list
# Report the usages of one migration without changing files
npm run deprecation:migrate -- --id component.sw-card
# Apply only the fixes that keep the behaviour
npm run deprecation:migrate -- --id component.sw-card --fix
# Also apply the fixes that leave a review comment
npm run deprecation:migrate -- --id component.sw-card --fix --level review
```

`--level` sets how much `--fix` may change.
Run the levels in this order and commit after each one, so each commit has one kind of change:

1. `safe` (the default) applies only fixes that keep the behaviour.
   A component is converted together with all of its follow-up fixes, or not at all.
   The commit needs no review.
2. `review` also applies fixes that leave a `TODO Codemod` comment, but converts no component that needs manual work.
   Review the comments before you commit.
3. `partial` converts every component and puts a `TODO Codemod` comment above each part that needs manual work.
   Do that work before you commit.
4. `manual` changes no code and only puts a `TODO Codemod` comment above every deprecated usage.

ESLint configs choose the level with the `sw-deprecation-rules` setting; without it, the rules run at `safe`:

```js
settings: { 'sw-deprecation-rules': { level: 'review' } },
```

A step that is safe only for some usages says so per usage.
For example, `childElementsToArrayProp` turns static `<option>` elements into the `options` prop of `mt-select` at `safe`;
options from a `v-for` list need a review, because the list has to be an array.

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
