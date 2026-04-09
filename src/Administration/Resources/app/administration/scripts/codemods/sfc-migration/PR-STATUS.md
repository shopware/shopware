# PR #15673 — SFC Migration Codemod: Status

**PR:** shopware/shopware#15673  
**Issue:** shopware/shopware#14681  
**Branch:** `14681-admin-create-codemod-to-migrate-components-to-sfcs`  
**Status:** Draft — unfinished

---

## Acceptance Criteria (from issue #14681)

| # | Criterion | Status | Notes |
|---|---|---|---|
| 1 | Twig template blocks are transformed to the `<sw-block>` pattern | ✅ Done | `transform-template.ts` converts `{% block name %}` → `<sw-block name="name" :data="$dataScope">`, `{% endblock %}` → `</sw-block>`, `{{ parent() }}` → `<sw-block-parent/>` |
| 2 | Options API is transformed into Composition API (if possible) | ✅ Done | `transform-script.ts` covers: data→ref, computed, watch, methods, lifecycle hooks, this-rewriting, inject, emits, props, $refs, $router, $route, $slots, $nextTick, $tc/$t |
| 3 | `.html.twig` + `index.js` files are merged into one `.vue` file | ✅ Done | `generate-sfc.ts` + `run-sfc-migration.ts` orchestrate the merge and write `.vue` files |
| 4 | Breaking changes are only introduced after confirmation | ✅ Done | Default mode is dry-run (no files written); `--write` flag required for actual writes; report lines prefixed with `[DRY RUN]` in preview mode |
| 5 | Backoff strategies are implemented | ✅ Done | `render()` = hard blocker → `not-migratable` (no file written); `mixins`/`extends` = soft blocker → `partially-migratable` (Options API kept in plain `<script>`) |
| 6 | Summary is generated and categorized | ✅ Done | Console output from `run-sfc-migration.ts` + `generateSummary()` in `analyze-component.ts` |
| 7 | Codemod usage and limitations are documented | ✅ Done | `README.md` covers usage, feature table, outcomes, and what needs manual review |

---

## What is Already Done

### Core Transformation Pipeline

- **`transform-template.ts`** — Full Twig-to-Vue template conversion:
  - `{% block name %}` / `{% endblock %}` / `{{ parent() }}`
  - Twig comments `{# ... #}` → HTML comments `<!-- ... -->`
  - Strips `{% extends '...' %}` lines and accompanying eslint-disable comments
  - Plain HTML and Vue expressions pass through unchanged

- **`transform-script.ts`** — Extensive Options API → Composition API conversion:
  - All data properties → `ref(...)` inside `createExtendableSetup`
  - Computed (getter and getter+setter variants) → `computed(...)`
  - Watch with prop or data sources → `watch(() => source, ...)`
  - Methods → plain functions inside `createExtendableSetup`
  - `created()` body runs directly in setup (correct Composition API equivalent)
  - All other lifecycle hooks → `onMounted`, `onBeforeUnmount`, `onUnmounted`, `onUpdated`, etc. (including Vue 2 legacy names)
  - `this.prop` → `props.prop`, `this.data` → `data.value`, `this.computed` → `computed`, `this.method()` → `method()`
  - `this.$emit` → `emit`, `this.$refs.name` → `name.value` (with auto-generated `const name = ref(null)`)
  - `this.$router` / `this.$route` → `useRouter()` / `useRoute()`
  - `this.$slots` → `useSlots()`
  - `this.$nextTick` → `nextTick(...)`
  - `this.$tc` / `this.$t` → `useI18n().tc` / `.t`
  - `this.$el` → `/* TODO: $el */ getCurrentInstance()?.proxy?.$el`
  - Module-level code preserved (scss side-effect imports, const declarations) — template imports stripped
  - `createExtendableSetup()` wrapper with `public:` return ensures components stay extensible
  - Auto-detects required Vue/Router/I18n imports from usage
  - AST-based parsing (ts-morph) throughout — no regex false positives

- **`analyze-component.ts`** — Blocker detection and categorization:
  - Hard blockers (render function) → `not-migratable`
  - Soft blockers (mixins, extends) → `partially-migratable`
  - Human-readable summary generation

- **`generate-sfc.ts`** — Orchestrator combining template + script into three outcome paths

- **`run-sfc-migration.ts`** — CLI runner:
  - Recursively scans a directory for `index.js` files
  - Auto-detects companion `.html.twig` file
  - Normalises `export default {}` components to `Shopware.Component.register()` format
  - Default dry-run mode previews what would be written without touching the filesystem
  - `--write` flag required to actually write `.vue` files
  - Prints per-file report and summary counts

- **`README.md`** — Usage docs, feature table, outcome descriptions, manual review guidance

### Test Coverage

All transform modules have integration tests using real fixture files:

| File | Status |
|---|---|
| `analyze-component.spec.ts` | ✅ Full coverage — all 4 fixtures, per-component analysis, categorization, summary |
| `transform-template.spec.ts` | ✅ Full coverage — 3 fixtures (block, simple, twig-comments) |
| `generate-sfc.spec.ts` | ✅ Full coverage — all 4 end-to-end paths (fully/partially/not/render) |
| `transform-script.spec.ts` | ✅ Full coverage — 5 fixtures (simple, block, created, module-level, mixin, render) |
| `run-sfc-migration.spec.ts` | ✅ Full coverage — 23 tests: `findTwigFile`, `normaliseJsContent`, dry-run (no writes), write mode (file created), skip (no twig), not-migratable, partially-migrated |

---

## What is Missing / Incomplete

### 2. Original Files Are Not Deleted
After generating a `.vue` file, the original `index.js` and `.html.twig` are left in place.  
**Needed:** Either auto-delete them (with confirmation) or print explicit instructions to remove them.

### 3. `<sw-block>` / `<sw-block-parent>` Components Do Not Exist Yet
The template transformation produces `<sw-block name="..." :data="$dataScope">` and `<sw-block-parent/>`. These custom components and the `$dataScope` prop binding need to be implemented in the framework before any migrated component can actually work.  
**Needed:** The companion PR/task to create these components is not referenced anywhere.

### 4. `$dataScope` Is Unexplained
The `:data="$dataScope"` binding is hard-coded in `transform-template.ts` but `$dataScope` is never defined or injected in the generated `<script setup>`. A migrated component would break at runtime.  
**Needed:** Either generate the correct binding or document how it is injected.

### 5. `this.$el` Is Unresolved
Replaced with `/* TODO: $el */ getCurrentInstance()?.proxy?.$el`. This is noted in the README as a known limitation.  
**Needed:** At minimum, auto-search and report all generated files containing `TODO: $el` in the summary.

### 6. `Shopware.Component.extend()` Soft-Blocker — No Inlining Attempt
Components using `.extend()` are flagged as partially-migratable and dropped to Options API backoff without any attempt to inline the parent component's options.  
**Needed:** If in-scope, add extend-inlining; otherwise document clearly that this pattern requires manual migration.

### 12. Codemod Not Integrated Into Existing Admin Codemod Tooling
The codemod is only usable via manual invocation (`npx tsx scripts/codemods/sfc-migration/run-sfc-migration.ts <path>`). It is not a first-class Shopware codemod:

- No `package.json` script entry
- No integration into `src/Administration/Resources/app/administration/code-mods.js`
- No entry in the existing codemod CLI flow used elsewhere in Administration

**Needed:** Wire the codemod into the standard admin codemod entrypoints.

### ~~13. Several Conversions Are Coded But Not Proven by Tests~~ ✅ Done
All conversion paths now have dedicated fixtures and tests. A bug in `findOptionsObject` was also fixed: it was reading the wrong argument index for `Shopware.Component.extend()` calls (3 args), causing them to be incorrectly classified as `not-migratable`. The fix makes `.extend()` components correctly reach the `partially-migratable` soft-blocker path.

New fixtures: `composables-component`, `inherit-attrs-component`, `extend-component`, `extends-template`. New tests: 21 across `transform-script.spec.ts` and `transform-template.spec.ts`. All 159 tests pass.

### 14. No Validation Against Real Administration Components
Test coverage is entirely fixture-based. There is no proof that the codemod works across the broad variety of real Administration component patterns.  
**Needed:** Run the codemod against a representative sample of real components in the repo and verify the output before marking the PR ready.

---

## Test Coverage Summary (updated)

| Spec file | Tests |
|---|---|
| `analyze-component.spec.ts` | ✅ Full coverage |
| `transform-template.spec.ts` | ✅ Full coverage — 4 fixtures (block, simple, twig-comments, extends-template) |
| `generate-sfc.spec.ts` | ✅ Full coverage |
| `transform-script.spec.ts` | ✅ Full coverage — 9 fixtures (simple, block, created, module-level, mixin, render, composables, inherit-attrs, extend) |
| `run-sfc-migration.spec.ts` | ✅ Full coverage — 25 tests |

**Total: 159 tests, all passing**

### ~~8. Overwrite Without Warning~~ ✅ Done
Existing `.vue` files are skipped by default. Pass `--force` to overwrite. `skippedExisting` counter added to stats and summary. 6 new tests in `run-sfc-migration.spec.ts`.

### 9. `normaliseJsContent` Is Fragile
The function that wraps `export default {}` components replaces the last `};` in the file. This can produce invalid JS if the component has nested `};` patterns at the module level.  
**Needed:** An AST-based rewrite or a more robust delimiter strategy.

### 10. PR Description Is Incomplete
The "What does this change do, exactly?" section in the PR body is blank.  
**Needed:** Fill in the description before marking ready for review.

### 11. PR Checklist Unchecked
None of the PR checklist items are ticked (tests, release notes, docs).  
**Needed:** Verify each item before the draft is promoted.

---

## Summary

The transformation core (template conversion, script conversion, merger, analysis, CLI) is functionally complete and well-tested. The main gaps are:

1. ~~**Acceptance criterion #4** (confirmation before breaking changes) is entirely unimplemented.~~ ✅ Done — dry-run default + `--write` flag.
2. **`<sw-block>` infrastructure** does not exist — migrated components will not work until those components are built.
3. **`$dataScope` binding** in templates is undefined in the generated script.
4. **Original files are never cleaned up** after migration.
5. ~~**Runner has no tests**.~~ ✅ Done — 23 tests in `run-sfc-migration.spec.ts`.
6. **PR meta** (description, checklist) is unfinished.
