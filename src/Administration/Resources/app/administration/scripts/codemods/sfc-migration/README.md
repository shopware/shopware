# SFC Migration Codemod

Automatically converts Shopware Administration components from the Options API (`index.js` + `.html.twig`) to Vue 3 Single File Components (`<script setup>`).

## Requirements

- Node.js 18+
- Access to the `administration` package (for `ts-morph`, `glob`, etc.)

## Usage

Pass the directory you want to migrate as the first argument:

```bash
npx tsx scripts/codemods/sfc-migration/run-sfc-migration.ts <path>
```

The script scans `<path>` recursively for `index.js` files and converts every component it finds alongside a `.html.twig` file.

**Examples:**

```bash
# Migrate a single component
npx tsx run-sfc-migration.ts src/Resources/app/administration/src/component/my-component

# Migrate an entire plugin's administration folder
npx tsx run-sfc-migration.ts src/Resources/app/administration/src
```

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

- **`this.$el`** — no direct equivalent; replaced with `/* TODO: $el */ getCurrentInstance()?.proxy?.$el`
- **Partially migrated components** — mixins and `extends` must be manually inlined
- **Render functions** — must be rewritten as templates by hand
