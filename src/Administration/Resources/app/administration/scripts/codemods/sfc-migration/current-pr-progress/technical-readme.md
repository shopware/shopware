# SFC Migration Codemod — Technical Reference

**PR:** shopware/shopware#15673
**Issue:** shopware/shopware#14681
**Branch:** `14681-admin-create-codemod-to-migrate-components-to-sfcs`

---

## What this codemod does

Shopware Administration components historically consist of two files:

- `index.js` — component logic in Options API style, registered via `Shopware.Component.register()`
- `*.html.twig` — template using Twig block syntax for theme/plugin overridability

This codemod merges and transforms those two files into a single `.vue` SFC using:

- **`<template>`** — converted from Twig (blocks become `<sw-block>` elements)
- **`<script setup>`** — converted from Options API to Composition API (data → `ref`, computed → `computed`, etc.)

The output is intentionally wrapped in `createExtendableSetup()` to preserve Shopware's component extension mechanism.

---

## Directory layout

```
scripts/codemods/sfc-migration/
├── transform-template.ts         # Twig → Vue <template> conversion
├── transform-script.ts           # Options API → <script setup> conversion
├── analyze-component.ts          # Blocker detection and migration categorization
├── generate-sfc.ts               # Orchestrator: merges template + script into .vue
├── run-sfc-migration.ts          # CLI entry point
├── README.md                     # User-facing usage guide
├── PR-STATUS.md                  # Acceptance criteria tracker + known gaps
├── current-pr-progress/          # ← you are here
│   ├── technical-readme.md
│   └── *.unfinished.md           # One file per open gap
└── __fixtures__/                 # Test fixture components
    ├── simple-component.index.js
    ├── simple-component.html.twig
    ├── block-component.index.js
    ├── block-component.html.twig
    ├── created-component.index.js
    ├── created-component.html.twig
    ├── module-level-component.index.js
    ├── module-level-component.html.twig
    ├── composables-component.{index.js,html.twig}  # $router/$route/$slots/$nextTick/$t/$tc/$el
    ├── inherit-attrs-component.{index.js,html.twig} # inheritAttrs: false
    ├── mixin-component.index.js   # soft blocker: mixins (no twig)
    ├── extend-component.index.js  # soft blocker: Shopware.Component.extend() (no twig)
    ├── render-component.index.js  # hard blocker: render() (no twig)
    ├── twig-comments.html.twig    # template-only: {# ... #} comments
    └── extends-template.html.twig # template-only: {% extends %} stripping
```

---

## Transformation pipeline

```
index.js + *.html.twig
        │
        ▼
  analyze-component.ts        ← detects blockers, assigns migration status
        │
        ├─ not-migratable ──► skip (no .vue written)
        │
        ├─ partially-migratable ──► generate-sfc.ts ──► <template> + plain <script> (Options API kept)
        │
        └─ fully-migratable ──► generate-sfc.ts
                                    ├── transform-template.ts   (twig → <template>)
                                    └── transform-script.ts     (Options API → <script setup>)
                                          │
                                          ▼
                                     .vue SFC file
```

The CLI (`run-sfc-migration.ts`) walks a directory, feeds each `index.js` + companion `.html.twig` through this pipeline, and writes `.vue` files.

---

## Module details

### `transform-template.ts`

Converts Twig template content into a Vue `<template>` section.

**Approach:** Regex-based (Twig is line-oriented; no nesting rules requiring AST).

| Input | Output |
|-------|--------|
| `{% block name %}` | `<sw-block name="name" :data="$dataScope">` |
| `{% endblock %}` | `</sw-block>` |
| `{{ parent() }}` | `<sw-block-parent/>` |
| `{# comment #}` | `<!-- comment -->` |
| `{% extends '...' %}` | (line removed) |
| HTML / Vue expressions | unchanged |

The `{% extends %}` line and any adjacent eslint-disable comment are stripped since the inheritance relationship is expressed structurally in Twig but has no equivalent in the SFC model.

**Known issue:** The `<sw-block>` and `<sw-block-parent>` components referenced here do not exist yet. See [sw-block-components-missing.unfinished.md](sw-block-components-missing.unfinished.md).

---

### `transform-script.ts`

Converts an Options API component into a `<script setup>` block using `ts-morph` for AST-based parsing.

**Entry point:** `transformScript(jsContent: string): TransformScriptResult`

Returns:
```ts
{
    script: string,
    scriptType: 'setup' | 'options',
    status: MigrationStatus,
    blockers: string[],
}
```

**Three outcomes:**

| Status | `scriptType` | What happens |
|--------|-------------|--------------|
| `fully-migratable` | `'setup'` | Full Composition API output with `createExtendableSetup` |
| `partially-migratable` | `'options'` | Original Options API kept; only template import stripped |
| `not-migratable` | — | Empty string; no file written |

**Blockers:**

- Hard blocker: `render function` → `not-migratable`
- Soft blockers: `mixins`, `extends` → `partially-migratable`

**Options API → Composition API mapping:**

| Options API | Composition API |
|-------------|----------------|
| `data() { return { x } }` | `const x = ref(...)` |
| `computed: { a() {} }` | `const a = computed(() => {})` |
| `computed: { a: { get, set } }` | `const a = computed({ get, set })` |
| `watch: { x(newVal) {} }` | `watch(() => x.value, (newVal) => {})` |
| `watch: { propName(newVal) {} }` | `watch(() => props.propName, ...)` |
| `methods: { fn() {} }` | `const fn = () => {}` |
| `created() {}` | Body runs directly in setup (no hook wrapper) |
| `mounted() {}` | `onMounted(() => {})` |
| `beforeUnmount() {}` | `onBeforeUnmount(() => {})` |
| `unmounted() {}` | `onUnmounted(() => {})` |
| `inject: ['x']` | `const x = inject('x')` |
| `props: {...}` | `defineProps({...})` |
| `emits: ['e']` | `defineEmits(['e'])` |
| `inheritAttrs: false` | `defineOptions({ inheritAttrs: false })` |

**`this` rewriting rules (in order):**

| `this.x` pattern | Rewritten to |
|------------------|-------------|
| `this.$refs.name` | `name.value` (+ auto-declares `const name = ref(null)`) |
| `this.$emit(...)` | `emit(...)` |
| `this.$router` | `router` (from `useRouter()`) |
| `this.$route` | `route` (from `useRoute()`) |
| `this.$nextTick` | `nextTick` |
| `this.$slots` | `slots` (from `useSlots()`) |
| `this.$props` | `props` |
| `this.$tc` / `this.$t` | `tc` / `t` (from `useI18n()`) |
| `this.$el` | `/* TODO: $el */ getCurrentInstance()?.proxy?.$el` |
| `this.propName` | `props.propName` |
| `this.dataName` | `dataName.value` |
| `this.computedName` | `computedName.value` |
| `this.methodName` | `methodName` |
| `this.injectKey` | `injectKey` |

**Output structure** (fully-migratable):

```js
// module-level code (SCSS imports, const declarations, etc.)

defineProps({...})
const emit = defineEmits([...])

import { createExtendableSetup } from 'src/app/adapter/composition-extension-system';
import { ref, computed, ... } from 'vue';
// conditional: import { useRouter, useRoute } from 'vue-router';
// conditional: const { t, tc } = useI18n();

const refName = ref(null); // auto-generated template refs

const {
    prop1, data1, computed1, method1,
} = createExtendableSetup(
    { name: 'component-name', props },
    () => {
        const inject1 = inject('inject1');
        const data1 = ref('initial');
        const computed1 = computed(() => { ... });
        const method1 = () => { ... };
        onMounted(() => { ... });

        return {
            public: { inject1, data1, computed1, method1 },
        };
    },
);
```

---

### `analyze-component.ts`

Runs blocker detection without producing any output files.

**Functions:**
- `analyzeComponent(name, jsContent): ComponentAnalysis` — returns `{ componentName, status, blockers }`
- `categorizeComponents(analyses): MigrationCategories` — groups into three arrays
- `generateSummary(categories): string` — human-readable report

Used by `run-sfc-migration.ts` for the final summary printout.

---

### `generate-sfc.ts`

Combines template and script results into a `.vue` file string.

**Function:** `mergeComponentFiles(twigContent, jsContent): MergeResult`

1. Runs `transformScript` → if `not-migratable`, returns empty SFC immediately
2. Runs `transformTemplate`
3. Wraps in `<template>...</template>\n\n<script setup>...</script>` (or `<script>` for backoff)

---

### `run-sfc-migration.ts`

CLI runner. Invoked via (from `src/Administration/Resources/app/administration/`):

```bash
# Preview only (default — no files written)
npm run codemod:sfc-migration -- <target-directory>
npm run codemod:sfc-migration -- --dry-run <target-directory>

# Write .vue files to disk (skips existing .vue files by default)
npm run codemod:sfc-migration -- --write <target-directory>

# Write .vue files, overwriting any that already exist
npm run codemod:sfc-migration -- --write --force <target-directory>

# Write .vue files and delete the source index.js + .html.twig afterwards
npm run codemod:sfc-migration -- --write --delete-originals <target-directory>
```

**What it does:**
1. Globs `**/index.js` recursively under the target directory
2. For each file: finds companion `.html.twig` in the same directory (skips if missing)
3. Normalises `export default {}` → `Shopware.Component.register()` via `normaliseJsContent`
4. Calls `mergeComponentFiles`
5. In `--write` mode: writes `<component-name>.vue` next to the `index.js`; in dry-run (default): prints what would be written without touching the filesystem
6. Prints per-file result and a final summary; appends `[DRY RUN]` footer when no files were written

**Output symbols:**
- `✓` — fully-migrated
- `~` — partially-migrated (with blocker names)
- `✗` — not-migratable (with blocker names)
- `SKIP (no twig)` — no companion `.html.twig` found

---

## Test structure

All tests use real fixture files from `__fixtures__/` and Jest snapshot testing.

| Spec file | What it tests | Fixtures used |
|-----------|---------------|---------------|
| `analyze-component.spec.ts` | Blocker detection, categorization, summary report | simple, block, mixin, render |
| `transform-template.spec.ts` | Twig → Vue template conversion | block, simple, twig-comments, extends-template |
| `transform-script.spec.ts` | Options API → Composition API | simple, block, created, module-level, mixin, render, composables, inherit-attrs, extend |
| `generate-sfc.spec.ts` | End-to-end pipeline | simple, block, mixin, render |
| `run-sfc-migration.spec.ts` | CLI runner: `findTwigFile`, `normaliseJsContent`, dry-run, `--write`, skip, not-migratable, partially-migrated | simple, render, mixin + temp dirs |

Snapshots live in `__snapshots__/` and are updated with `--updateSnapshot`.

---

## Fixtures quick reference

| Fixture | Migration status | Key features tested |
|---------|-----------------|---------------------|
| `simple-component` | fully-migratable | inject, data, computed, methods, `$emit` |
| `block-component` | fully-migratable | Twig blocks, props, emits, computed getter+setter, watch (data + prop), `$refs`, lifecycle |
| `created-component` | fully-migratable | `created()` as direct setup code, `beforeUnmount`, `unmounted` |
| `module-level-component` | fully-migratable | SCSS import, `const` declarations, module-level Shopware utils |
| `composables-component` | fully-migratable | `$router`, `$route`, `$slots`, `$nextTick`, `$t`, `$tc`, `$el` |
| `inherit-attrs-component` | fully-migratable | `inheritAttrs: false` → `defineOptions({ inheritAttrs: false })` |
| `mixin-component` | partially-migratable | Soft blocker: `mixins` |
| `extend-component` | partially-migratable | Soft blocker: `Shopware.Component.extend()` |
| `render-component` | not-migratable | Hard blocker: `render()` function |
| `twig-comments` | (template only) | `{# ... #}` → `<!-- ... -->` |
| `extends-template` | (template only) | `{% extends '...' %}` stripping, adjacent eslint-disable removal |

---

## Key design decisions

**Why `createExtendableSetup`?**
Shopware's component extension system (`overrideComponentSetup`) requires all component state to be accessible and overridable. Wrapping setup in `createExtendableSetup` with a `public:` return preserves this after migration to Composition API.

**Why ts-morph for JS, regex for Twig?**
Twig is line-oriented template syntax — regex is sufficient and simpler. JavaScript has complex nesting and scoping that requires a proper AST to handle without false positives. `ts-morph` parses TypeScript/JavaScript into a full AST and provides typed query APIs.

**Why soft blockers keep Options API?**
`mixins` and `extends` involve merging multiple component definitions. Automating this correctly requires understanding the mixin's implementation, which is out of scope for a syntax-level codemod. Keeping Options API lets developers migrate these manually.

---

## Open gaps

Each item below has a corresponding `.unfinished.md` file in this directory:

| File | Summary |
|------|---------|
| [confirmation-before-writes.finished.md](confirmation-before-writes.finished.md) | ✅ `--dry-run` default + `--write` flag implemented; runner tests added |
| [original-files-cleanup.finished.md](original-files-cleanup.finished.md) | ✅ `--delete-originals` flag deletes source files after writing `.vue`; 11 new tests |
| [sw-block-components-missing.unfinished.md](sw-block-components-missing.unfinished.md) | `<sw-block>` / `<sw-block-parent>` Vue components don't exist yet |
| [data-scope-binding.unfinished.md](data-scope-binding.unfinished.md) | `$dataScope` referenced in templates but never defined in generated script |
| [this-el-resolution.finished.md](this-el-resolution.finished.md) | ✅ Runner flags `$el` usage with `⚠` report lines and `elWarnings` stat; README updated |
| [extend-soft-blocker-inlining.unfinished.md](extend-soft-blocker-inlining.unfinished.md) | `Shopware.Component.extend()` triggers backoff with no inlining attempt |
| [runner-tests.finished.md](runner-tests.finished.md) | ✅ 23 tests in `run-sfc-migration.spec.ts` — `findTwigFile`, `normaliseJsContent`, dry-run, write, skip, not-migratable, partial |
| [overwrite-protection.finished.md](overwrite-protection.finished.md) | ✅ Skip existing `.vue` by default; `--force` flag to overwrite; `skippedExisting` counter |
| [normalise-js-content-fragile.finished.md](normalise-js-content-fragile.finished.md) | ✅ `normaliseJsContent` rewritten with ts-morph AST; 2 regression tests added |
| [pr-description.unfinished.md](pr-description.unfinished.md) | PR description body is empty |
| [pr-checklist.unfinished.md](pr-checklist.unfinished.md) | PR checklist items are all unchecked |
| [codemod-tooling-integration.finished.md](codemod-tooling-integration.finished.md) | ✅ `npm run codemod:sfc-migration` added to `package.json`; `import.meta.url` replaced with CJS `__filename` |
| [untested-conversions.finished.md](untested-conversions.finished.md) | ✅ All conversion paths now have dedicated fixtures and tests; `findOptionsObject` bug fixed |
| [real-component-validation.unfinished.md](real-component-validation.unfinished.md) | No validation run against real Administration components |
