# SFC Migration Codemod

Automatically converts Shopware Administration components from the Options API (`index.js` + `.html.twig`) to Vue 3 Single File Components (`<script setup>`).

## Requirements

- Node.js 18+
- Access to the `administration` package (for `ts-morph`, `glob`, etc.)

## Usage

Run from inside `src/Administration/Resources/app/administration/`:

```bash
# Preview what would be migrated (default — no files written)
npm run codemod:sfc-migration -- <path>
npm run codemod:sfc-migration -- --dry-run <path>

# Write .vue files to disk (skips existing .vue files)
npm run codemod:sfc-migration -- --write <path>

# Overwrite existing .vue files
npm run codemod:sfc-migration -- --write --force <path>

# Write .vue files and delete the source index.js + .html.twig afterwards
npm run codemod:sfc-migration -- --write --delete-originals <path>
```

**Examples:**

```bash
# Migrate a single component
npm run codemod:sfc-migration -- src/app/component/base/sw-button

# Migrate an entire plugin's administration folder
npm run codemod:sfc-migration -- --write src/Resources/app/administration/src
```

Pass `<path>` relative to the `administration/` directory, or use an absolute path.

Components are expected to follow this structure:

```
my-component/
├── index.js                  ← Shopware.Component.register / .extend or export default {}
└── my-component.html.twig
```

## What gets converted automatically

| Options API | Composition API output |
|---|---|
| `props` | `defineProps(…)` |
| `emits` | `defineEmits(…)` |
| `inheritAttrs: false` | `defineOptions({ inheritAttrs: false })` |
| `data()` | `ref(…)` inside `createExtendableSetup` |
| `computed` | `computed(…)` inside `createExtendableSetup` |
| `watch` | `watch(…)` inside `createExtendableSetup` |
| `methods` | plain functions inside `createExtendableSetup` |
| `created` | runs directly in setup (equivalent behaviour) |
| other lifecycle hooks | `onMounted`, `onBeforeUnmount`, etc. |
| `this.$emit` | `emit(…)` |
| `this.$router` / `this.$route` | `useRouter()` / `useRoute()` |
| `this.$slots` | `useSlots()` |
| `this.$nextTick` | `nextTick(…)` |
| `this.$tc` / `this.$t` | `useI18n().tc` / `.t` |
| `this.$refs.name` | `const name = ref(null)` |
| Twig `{# comments #}` | `<!-- HTML comments -->` |

## Migration outcomes

Each component is classified into one of three states:

| Status | Meaning | Output |
|---|---|---|
| `fully-migrated` | Full `<script setup>` with `createExtendableSetup` | `.vue` file written |
| `partially-migrated` | Soft blocker found (mixins, `extends`) — Options API kept in plain `<script>` | `.vue` file written, manual follow-up required |
| `not-migratable` | Hard blocker found (`render()`) — cannot be automatically converted | No file written |

## Programmatic API

```ts
import { mergeComponentFiles } from './generate-sfc';

const result = mergeComponentFiles(twigContent, jsContent);

if (result.status === 'fully-migrated') {
    fs.writeFileSync('my-component.vue', result.sfc);
}

// result.blockers — list of detected blockers (e.g. ['mixins', 'extends'])
```

## What needs manual review

After running the codemod, search for `TODO` comments in the generated files:

- **`this.$el`** — no direct equivalent; replaced with `/* TODO: $el */ getCurrentInstance()?.proxy?.$el`.
  The migration summary prints a `⚠` warning line for every component containing this pattern.
  Two cases arise:

  1. **Root element access in setup / lifecycle hooks** — prefer a template ref on the root element:
     ```html
     <template>
       <div ref="rootEl">…</div>
     </template>
     ```
     ```ts
     const rootEl = ref<HTMLElement | null>(null);
     onMounted(() => { rootEl.value?.focus(); });
     ```
  2. **Dynamic DOM access inside methods** — `getCurrentInstance()?.proxy?.$el` is a valid transitional
     bridge, but note that `getCurrentInstance()` returns `null` when called outside of the synchronous
     setup phase. If the method runs after setup completes, store the element in a template ref instead.

- **Partially migrated components** — mixins and `extends` must be manually inlined
- **Render functions** — must be rewritten as templates by hand
