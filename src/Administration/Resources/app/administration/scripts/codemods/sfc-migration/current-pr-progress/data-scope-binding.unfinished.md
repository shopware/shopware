# Missing: `$dataScope` Is Undefined in Generated Script

**Status:** Runtime blocker — every migrated component with Twig blocks will fail because `$dataScope` is never defined.

---

## What the codemod generates

`transform-template.ts` outputs this for every Twig block:

```html
<sw-block name="sw_card_header" :data="$dataScope">
  ...
</sw-block>
```

The `:data="$dataScope"` binding is hard-coded. However, `transform-script.ts` never generates a `$dataScope` variable — it does not appear anywhere in the `<script setup>` output.

At runtime Vue will warn: `$dataScope is not defined` and the binding will be `undefined`.

---

## What `$dataScope` is supposed to be

In the Twig block system, blocks have access to the component's data scope (all reactive state). `$dataScope` is intended to pass this scope to `<sw-block>` so that the block override system can inject it into override slots.

The concept is: a block override might use `{{ title }}` — it needs the parent component's `title` ref to be available.

---

## Options for resolution

### Option A: Auto-generate `$dataScope` in the script transform

After building the `createExtendableSetup` block, generate a `$dataScope` constant exposing all public state:

```ts
const $dataScope = { title, isLoading, onSave, ... };
```

This is the data that's already in the `public:` return key of `createExtendableSetup`, so it could be derived from the same list.

**Pros:** Fully automatic, no manual step needed.
**Cons:** Tight coupling between the scope object shape and the override system's expectations.

### Option B: Inject `$dataScope` via `<sw-block>` itself

The `<sw-block>` component (once built — see [sw-block-components-missing.unfinished.md](sw-block-components-missing.unfinished.md)) could auto-inject the parent component instance's exposed state as the data scope, making the `:data="$dataScope"` prop unnecessary or computed internally.

**Pros:** No generated code needed.
**Cons:** Requires `<sw-block>` to reach into its parent's context, which is fragile.

### Option C: Remove the `:data` binding and redesign the API

Decide that `<sw-block>` does not need an explicit data scope prop — it derives context differently (e.g., via provide/inject from `createExtendableSetup`).

This would require changing `transform-template.ts` to not emit `:data="$dataScope"`.

---

## Recommended path

Coordinate with the team building `<sw-block>` (see [sw-block-components-missing.unfinished.md](sw-block-components-missing.unfinished.md)) to agree on the API first, then update `transform-template.ts` to generate the correct binding.

---

## Relevant files

- `transform-template.ts` — hard-codes `:data="$dataScope"` (search for `$dataScope`)
- `transform-script.ts` — never generates `$dataScope`
- `generate-sfc.ts` — assembles template + script; could inject `$dataScope` at merge time

---

## Acceptance check

- [ ] `$dataScope` is either defined in the generated `<script setup>` or the `:data` binding is removed
- [ ] Migrated components with blocks do not produce Vue warnings about undefined `$dataScope`
- [ ] The chosen approach is documented (update `README.md`)
- [ ] `transform-template.spec.ts` and `generate-sfc.spec.ts` snapshots updated to reflect the final approach
