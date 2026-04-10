# Resolved: `$dataScope` Is Now Generated in the Script

**Status:** ✅ Done — Option A implemented: `$dataScope` is auto-generated from the `public:` return of `createExtendableSetup`.

---

## What was the problem

`transform-template.ts` hard-coded `:data="$dataScope"` on every `<sw-block>` element, but `transform-script.ts` never generated a `$dataScope` variable. At runtime, Vue would warn `$dataScope is not defined` and the binding would be `undefined`.

---

## What was implemented

**Option A** from the original analysis: `$dataScope` is now auto-generated in `generate-sfc.ts` after both the template and script are available.

### Approach

1. `TransformScriptResult` gained a new `publicNames: string[]` field — the list of all names exposed in the `public:` return key of `createExtendableSetup` (inject keys + data props + computed props + methods).

2. `buildCompositionApiScript` in `transform-script.ts` now returns `{ script, publicNames }` instead of just a string. The three non-setup return paths return `publicNames: []`.

3. `generate-sfc.ts` conditionally appends a `$dataScope` constant to the script when the template section contains `$dataScope` (i.e., when the Twig source had any `{% block %}` tags):

```js
const $dataScope = { acl, title, description, count, canEdit, label, onAction, onReset };
```

This is placed after the `createExtendableSetup(...)` destructuring so all names are in scope.

### Why conditional

`$dataScope` is only emitted when the template actually uses it. Components with no Twig blocks produce no `$dataScope` binding in the template, so no unused variable is introduced.

---

## Files changed

- `transform-script.ts` — `TransformScriptResult` interface gains `publicNames`; `buildCompositionApiScript` returns `{ script, publicNames }`
- `generate-sfc.ts` — appends `const $dataScope = { ... };` when template contains `$dataScope`

## Tests

2 new assertions in `generate-sfc.spec.ts` (inside the `block-component` describe):
- `defines $dataScope after createExtendableSetup so <sw-block> can pass reactive state to overrides`
- `does not define $dataScope for components without twig blocks`

`generate-sfc.spec.ts.snap` updated: `block-component` snapshot now ends with the `$dataScope` line.

**Total: 187 tests, all passing.**

---

## Acceptance check

- [x] `$dataScope` is defined in the generated `<script setup>` for block-based components
- [x] Components with no blocks do not produce an unused `$dataScope`
- [x] Migrated block components will not produce Vue warnings about undefined `$dataScope`
- [x] Snapshot updated to reflect the final output
