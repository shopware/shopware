# SFC Migration Codemod

Automatically converts Shopware Administration components from the Options API (`index.js` + `.html.twig`) to Vue 3 Single File Components (`<script setup>`).

## Requirements

- Node.js 20+
- npm 10+
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

# Write .vue files, replace source index.js with an SFC entry point, and delete .html.twig afterwards
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

| Options API                               | Composition API output                         |
| ----------------------------------------- | ---------------------------------------------- |
| `props`                                   | `defineProps(…)`                               |
| `emits` array/object form                 | `defineEmits(…)`                               |
| `inheritAttrs: false`                     | `defineOptions({ inheritAttrs: false })`       |
| `name`                                    | `defineOptions({ name })`                      |
| `data()` / `data: () => ({ … })`          | `ref(…)` inside `createExtendableSetup`        |
| `computed`                                | `computed(…)` inside `createExtendableSetup`   |
| `inject` array/object form                | `inject(…)` inside `createExtendableSetup`     |
| `watch` method/object/string-handler form | `watch(…)` inside `createExtendableSetup`      |
| `methods`                                 | plain functions inside `createExtendableSetup` |
| `created`                                 | runs directly in setup (equivalent behaviour)  |
| other lifecycle hooks                     | `onMounted`, `onBeforeUnmount`, etc.           |
| `this.$emit`                              | `emit(…)`                                      |
| `this.$router` / `this.$route`            | `useRouter()` / `useRoute()`                   |
| `this.$slots`                             | `useSlots()`                                   |
| `this.$nextTick`                          | `nextTick(…)`                                  |
| `this.$tc` / `this.$t`                    | `useI18n().tc` / `.t`                          |
| `this.$refs.name`                         | `const name = ref(null)`                       |
| Twig `{# comments #}`                     | `<!-- HTML comments -->`                       |

Template transformation only supports Twig block tags (`{% block %}`, `{% endblock %}`, `{% parent %}`) and Twig comments. Templates containing Twig `{% extends '…' %}` fail the migration and must be handled manually before running the codemod.

## Migration outcomes

Each component is classified into one of three states:

| Status               | Meaning                                                                                           | Output                                         |
| -------------------- | ------------------------------------------------------------------------------------------------- | ---------------------------------------------- |
| `fully-migrated`     | Full `<script setup>` with `createExtendableSetup`                                                | `.vue` file written                            |
| `partially-migrated` | Soft blocker found (mixins, `Shopware.Component.extend()`) — Options API kept in plain `<script>` | `.vue` file written, manual follow-up required |
| `not-migratable`     | Hard blocker found (`render()`) — cannot be automatically converted                               | No file written                                |

## Programmatic API

```ts
import { mergeComponentFiles } from './generate-sfc';

const result = mergeComponentFiles(twigContent, jsContent);

if (result.status === 'fully-migrated') {
    fs.writeFileSync('my-component.vue', result.sfc);
}

// result.blockers — list of detected blockers (e.g. ['mixins', 'extends (parent: sw-button)'])
```

## ⚠ Destructive Operations

`--delete-originals` is **irreversible**. It replaces `index.js` with a generated
entry point that imports the new `.vue` file, and deletes `.html.twig` for every
component that produces a `.vue` file — including **partially-migrated** components
(those with unresolved blockers that still use Options API).

Before using `--delete-originals`:

1. Commit or stash all current changes to git.
2. Run with `--dry-run` first to review what would be written.
3. Verify the generated `.vue` files and replacement `index.js` entry points are correct before deletion.

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
        onMounted(() => {
            rootEl.value?.focus();
        });
        ```
    2. **Dynamic DOM access inside methods** — `getCurrentInstance()?.proxy?.$el` is a valid transitional
       bridge, but note that `getCurrentInstance()` returns `null` when called outside of the synchronous
       setup phase. If the method runs after setup completes, store the element in a template ref instead.

- **Partially migrated components** — mixins and `Shopware.Component.extend()` must be manually inlined
- **Render functions** — must be rewritten as templates by hand

## Manual migration: `extends`-based components

Components registered via `Shopware.Component.extend()` are partially migrated — the Options API is preserved in a plain `<script>` block. The migration report shows a `⚠` warning line with the parent component name:

```
~  partially-migrated  [extends (parent: sw-button)]  sw-extended-button.vue
   ⚠  manually inline parent options from 'sw-button' before re-running codemod; see README.md
```

Automatic inlining is out of scope for this codemod because it requires resolving and deep-merging the parent's implementation, which has too many edge cases (chained inheritance, circular references, parents that are themselves partially-migratable).

### Steps

1. **Find the parent component source** — the report shows the parent name, e.g. `sw-button`. Search for the
   parent component directory, usually `<parent-name>/index.js`, in the Administration source, module components,
   or the plugin administration source.

2. **Copy relevant options** — copy the parent options from that `index.js`: the `export default { ... }` object,
   or the object passed to `Shopware.Component.register()` / `Shopware.Component.extend()`. Merge the parent's
   `props`, `data`, `computed`, `methods`, and lifecycle hooks into the child, following
   [Vue 2's option merging strategy](https://v2.vuejs.org/v2/guide/mixins.html#Option-Merging):
    - `data`: deep-merged (child wins on conflict)
    - `methods` / `computed`: child overrides parent
    - lifecycle hooks: both run (parent first)

3. **Replace `.extend()` with `.register()`** using the merged options object:

    ```js
    // Before
    Shopware.Component.extend('sw-extended-button', 'sw-button', {
        data() { return { extraLabel: 'Extended' }; },
        methods: { getLabel() { return this.extraLabel; } },
    });

    // After — parent options manually merged in
    Shopware.Component.register('sw-extended-button', {
        // copied from sw-button/index.js
        props: { /* parent props */ },
        computed: { /* parent computed */ },
        data() { return { /* parent data */, extraLabel: 'Extended' }; },
        methods: {
            /* parent methods */
            getLabel() { return this.extraLabel; },
        },
    });
    ```

4. **Re-run the codemod** — the component should now be classified as `fully-migratable`
   (unless other blockers remain).

    ```bash
    npm run codemod:sfc-migration -- --write path/to/sw-extended-button
    ```

## Known Limitations

The following Options API features are **not automatically converted**. After migration,
search your codebase for the `TODO:` comments the codemod inserts, and resolve each one manually.

| Feature                       | Behavior                                    | How to fix                                              |
| ----------------------------- | ------------------------------------------- | ------------------------------------------------------- |
| `provide`                     | Drops with TODO comment                     | Add `provide(key, value)` calls manually in setup       |
| `components`                  | Drops silently                              | Verify components are globally registered; remove if so |
| `directives`                  | Drops with TODO comment                     | Register directives globally or inline in setup         |
| `beforeCreate`                | Drops with TODO comment                     | Move logic to top of `<script setup>`                   |
| `this.$store`                 | Inserts TODO comment                        | Migrate Vuex access to a composable                     |
| `this.$parent` / `this.$root` | Inserts TODO comment                        | Refactor to avoid parent traversal                      |
| Nested watch path `'a.b'`     | Leaves a TODO comment and skips the watcher | Write watcher manually                                  |
