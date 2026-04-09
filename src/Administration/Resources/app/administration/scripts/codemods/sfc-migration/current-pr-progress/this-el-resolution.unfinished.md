# Missing: `this.$el` Replacement Is a TODO Comment

**Status:** Partially handled — the code is replaced with a working but non-clean pattern and no tracking exists for affected components.

---

## Current behavior

`transform-script.ts` rewrites `this.$el` to:

```ts
/* TODO: $el */ getCurrentInstance()?.proxy?.$el
```

This is a functional stopgap — `getCurrentInstance()?.proxy?.$el` does access the root DOM element — but it has problems:

1. `getCurrentInstance()` must be called **synchronously during setup**. If `this.$el` was used inside a method or lifecycle hook that runs after setup, the call context is wrong and it returns `null`.
2. The TODO comment is invisible to the migration summary. There is no report of which files contain this pattern after a run.
3. `proxy.$el` is an internal Vue API that may break in future Vue versions.

---

## What needs to be done

### 1. Track affected components in the migration summary

The runner summary should flag any generated `.vue` file containing `TODO: $el`:

```
⚠  sw-some-component.vue — contains TODO: $el (manual review required)
```

This requires scanning the written file content (or the script transform result) for the TODO pattern and adding it to the per-file report.

### 2. Decide on the correct replacement

Usage of `$el` in Shopware components falls into two categories:

**a) Root element access in setup / lifecycle hooks:**
The correct Vue 3 pattern is a template ref on the root element:

```html
<template>
  <div ref="rootEl">...</div>
</template>
```

```ts
const rootEl = ref<HTMLElement | null>(null);
onMounted(() => {
  rootEl.value?.focus();
});
```

The codemod cannot add the `ref` attribute to the template automatically without knowing which element is the root. This requires manual follow-up.

**b) Dynamic root element access (e.g., third-party DOM manipulation):**
These cases genuinely need `getCurrentInstance()?.proxy?.$el` as a transitional bridge, and should be documented as known technical debt.

### 3. Update the README

Document that `this.$el` usage requires manual review and explain the recommended replacement approach.

---

## Relevant file

- `transform-script.ts` — `rewriteThisInBody` function, `this.$el` branch (search for `$el`)
- `run-sfc-migration.ts` — summary output, where the warning should be added

---

## Acceptance check

- [ ] The migration summary flags every output file that contains `TODO: $el`
- [ ] `README.md` documents the `$el` limitation and recommended manual fix
- [ ] At minimum, `transform-script.spec.ts` has a test case confirming the current replacement output
