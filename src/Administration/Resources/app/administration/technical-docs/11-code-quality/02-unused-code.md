# Unused code

> Status: Stable

ESLint checks one file at a time, so it can't tell whether a file, an export or a package is used anywhere.
[Fallow](https://fallow.tools) follows the imports through the whole Administration and reports unused files, exports, types and dependencies.

## Running it

Run the commands in `src/Administration/Resources/app/administration`, or the composer wrappers in the repository root.

| Command | What it does |
|---|---|
| `npm run lint:fallow` (`composer fallow:admin`) | Checks the project against `fallow-baseline.json` with the config in `.fallowrc.json` |
| `npm run lint:fallow:prune` (`composer fallow:admin:prune`) | Removes fixed entries from the baseline, never adds one |

`npm run lint:all` and `composer eslint:admin` run Fallow too, and so does the lint job in CI.

## The baseline

`fallow-baseline.json` lists the findings that existed when the check was added.
New code has to pass without adding to it.

- A new finding fails the check: remove the unused code or dependency.
- A fixed finding fails the check as well, with "matched no current issue".
  Run `npm run lint:fallow:prune` and commit the smaller baseline.
- Never run `fallow dead-code --save-baseline` on the existing baseline, it would record new findings as old ones.
  The CI job "Lint baselines do not grow" catches it: it counts the entries per category and fails when a category grows.
  A category that is new to the baseline only passes together with a change to `.fallowrc.json`.

The baseline is not a list of code that is safe to delete.
`@private` keeps its meaning, so every export without it is public API, and removing one follows the deprecation process.

## Exports that extensions import

Extensions import from `src/` and `@administration/` or use the `Shopware` global, and Fallow can't see that.
Tag an export with `@public` so Fallow skips it:

- In a file that the `Shopware` global exposes as a whole, like `src/core/service/util.service.ts` behind `Shopware.Utils`,
  every export that Fallow reports gets `@public`.
- In other files, only exports that extensions import from `src/` get it.
- An export with `@private` never gets `@public`.
  If Fallow reports it, it stays in the baseline.

```ts
/**
 * @public
 */
export function getCurrentInstance(): ComponentInstanceWithSetupContext | null {
```

Fallow only reads `@public` from a `/** */` block.
The `sw-deprecation-rules/private-feature-declarations` ESLint rule accepts `@public` like `@private`.
Store default exports are ignored in `.fallowrc.json`, because stores register themselves and extensions get them through `Shopware.Store.get()`.

## Dependencies

Dependency findings are fixed, never baselined.
`ignoreDependencies` in `.fallowrc.json` lists the packages that are used in a way Fallow can't follow.

- CLI tools that CI calls: `license-checker-rseidelsohn`
- Packages loaded by path: `vue-tsc`, `@jridgewell/remapping`
- Type or test imports of packages that come with another dependency: `@tiptap/*`, `@vue/runtime-core`, `eslint-plugin-vue`, `source-map-js`, `yaml`
