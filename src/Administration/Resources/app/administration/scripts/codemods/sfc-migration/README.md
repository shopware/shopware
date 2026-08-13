# SFC Migration Codemod

Automatically converts Shopware Administration components from the Options API (`index.js` + `.html.twig`) to Vue 3 Single File Components (`<script setup>`).

The generated SFCs are native `<script setup>` base components: the migrated state lives at the top level of the setup block and the public override API is declared with the `swDefinePublic({ … })` marker. The build-time transform lowers that into Shopware's extension runtime — see [Native Setup Authoring](../../../technical-docs/03-extensibility/07-native-setup-authoring.md).

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
| `name` differing from the registered name | `defineOptions({ name })`                      |
| `data()` / `data: () => ({ … })`          | `ref(…)` in `<script setup>`                   |
| `computed`                                | `computed(…)` in `<script setup>`              |
| `...mapPropertyErrors(…)` / `...mapCollectionPropertyErrors(…)` | one named `computed(…)` per property |
| `...mapState(store, ['a', 'b'])`          | one named `computed(…)` per key                |
| `inject` array/object form                | `inject(…)` in `<script setup>`                |
| `provide` object / method form            | `provide(key, value)` in `<script setup>`      |
| `expose: ['a', 'b']`                      | `defineExpose({ a, b })`                       |
| `watch` method/object/string-handler form | `watch(…)` in `<script setup>`                 |
| `watch` on a path (`'entity.name'`)       | `watch(() => entity.value?.name, …)`           |
| `methods`                                 | plain functions in `<script setup>`            |
| `created`                                 | runs directly in setup (equivalent behaviour)  |
| other lifecycle hooks                     | `onMounted`, `onBeforeUnmount`, etc.           |
| `this.$emit`                              | `emit(…)`                                      |
| `this.$router` / `this.$route`            | `useRouter()` / `useRoute()`                   |
| `this.$slots`                             | `useSlots()`                                   |
| `this.$nextTick`                          | `nextTick(…)`                                  |
| `this.$tc` / `this.$t`                    | `t(…)` from `useI18n()`                        |
| `this.$te`                                | `te(…)` from `useI18n()`                       |
| `this.$refs.name`                         | `const name = ref(null)`                       |
| Twig `{# comments #}`                     | `<!-- HTML comments -->`                       |

A computed spread hides its keys behind a helper call, so the whole entry used to be a manual `TODO`
— and every `this.<generatedName>` reading one of those keys then dropped its own member on top.
Three helpers build their getters from literal arguments only, so the codemod writes those getters
out as explicit named entries, each with a comment naming the spread it came from:

- `...mapPropertyErrors(entity, [props])` and `...mapCollectionPropertyErrors(collection, [props])` —
  one getter per property, named exactly as `map-errors.service.ts` names it
  (`camelCase('<entity>.<prop>.error')`), with the service's own body ported literally. The getter
  reads `this.<entity>`, so an entity the component does not declare drops the entry with a reason
  rather than reading nothing.
- `...mapState(store, ['a', 'b'])` — one getter per key, `return <store>.<key>;`. The store comes
  from an expression-bodied arrow (`() => Store.get('x')` → `Store.get('x')`), a string literal
  (`'x'` → `Shopware.Store.get('x')`), or an identifier (`useX` → `useX()`).

Everything else keeps the `TODO`: `mapPageErrors` (its argument is a cross-module config object),
the `mapState` object form (it renames keys), a block-bodied arrow (it can run statements before
returning the store), non-literal arguments, an unknown helper, and a helper reached through a
member expression rather than a plain identifier.

`defineProps` and `defineEmits` are hoisted above every module-level local, so a definition naming
one cannot be emitted into the macro. Most of those names are only a shorthand for a global, though,
and the codemod writes the global path back out instead of backing off: a module-level `const` whose
initializer resolves to a path rooted in `Shopware`, `window`, or `document` — including an alias of
an alias — is expanded at every value position in the definition.

```js
const { Criteria } = Shopware.Data; // →  type: Shopware.Data.Criteria
const utils = Shopware.Utils;
const { types } = utils; //             →  types.isObject  →  Shopware.Utils.types.isObject
```

The alias declarations themselves stay in the generated block for the rest of the setup body. What
keeps the blocker: `let`/`var` (reassignable between declaration and read), array destructuring or a
destructuring default, an initializer that is not global-rooted (an array literal, a local factory),
and a shorthand entry (`{ Criteria }`) — a path cannot be written in shorthand position. A name a
local binding inside the definition shadows is not a reference to the alias at all: it is neither
expanded nor blocking.

`provide` is converted when its keys are static: a `provide()` method — also as a `function` value —
whose body is exactly one `return` of an object literal, or a plain `provide: { … }` object, in both
cases with identifier or string-literal keys. The generated `provide(key, value)` calls are emitted
after the watchers and before the `created` body, which is where the Options API evaluates
`provide()`. Provided keys are not part of the public override API, so they are not listed in
`swDefinePublic({ … })`.

Every other shape keeps the manual `TODO` — naming the key and the reason — for the whole option,
because a partially migrated `provide` would change what descendants receive:

- an **arrow** value (`provide: () => ({ … })`), because Vue applies the option with
  `provideOptions.call(instance)` and an arrow ignores that, so its `this` is not the component
- an **async** or **generator** `provide()`, which in the Options API provides the own keys of the
  returned promise or generator object, not the listed ones
- computed keys, shorthand or spread entries, accessors, statements before the `return`
- a value whose `this` usage cannot be rewritten

`expose` becomes a `defineExpose({ … })` call at the end of the setup block. A `<script setup>`
component is closed by default, so the explicit list is what reopens the surface a parent reaches
through its template ref. Only the array-of-string-literals form is converted, and every listed name
must be a migrated `data`, `computed`, `methods`, or `inject` member — otherwise the whole option
keeps the manual `TODO` naming the entry, because exposing a subset would silently shrink that
surface. A name listed twice is emitted once. `expose: []` is dropped without a `TODO`: it closes the
instance completely in the Options API, which is what script setup already does.

One caveat is a property of the base lowering, not of this codemod: it renames every author binding
to `__swSetupAuthor_<name>` and leaves the `defineExpose` argument pointing at that alias, while the
template reads the override-resolved binding. So a parent's template ref always reaches the **base**
implementation of an exposed member — a plugin override of it is not visible through that ref.

A dotted `watch` key is a property path, not a member name: Vue walks the segments and stops as soon
as an intermediate value is falsy. The generated getter reproduces that with optional chaining on
every step below the root — `'item.price.net'` becomes `watch(() => props.item?.price?.net, …)` — and
the root segment resolves like any other watch target (prop, data, computed, or inject). Optional
chaining stops on `null`/`undefined` only, so the two differ for a falsy non-nullish intermediate
(`0`, `''`, `false`): Vue yields that value, the generated getter reads a property off it. Paths with
a segment that is not an identifier (brackets, spaces, reserved words) and paths whose root is not a
declared member keep the manual `TODO`.

Every converted `inject`, `data`, `computed`, and `methods` name is additionally listed in a
`swDefinePublic({ … })` marker at the end of the setup block — that is the public override API a
plugin can replace. The marker is mandatory, so a component without any of those options gets
`swDefinePublic({})`. Every other top-level binding (props, emits, template refs, composables) stays
normal component and template state; it is only excluded from the public override API and remains
reachable for overrides through the `_private` group.

The override target name is no longer passed to a wrapper: it comes from the `.vue` filename. The
codemod therefore names the file after the registered component name and falls back to the component
directory name only when the registration has no literal name. Because the filename already carries
the name, a `name` option repeating the registered name is dropped; a `name` option that differs is
still emitted as `defineOptions({ name })`. With `--delete-originals`, originals are kept and
a warning is printed when the registered component name differs from the directory name, because the
generated entry point would import the wrong file.

A member named like one of the imports the generated setup writes (`ref`, `computed`, `watch`,
`onMounted`, `useRouter`, …) cannot be declared next to that import, so it is dropped with a `TODO`
naming the collision — rename the member and migrate the component again. When the component's own
module already imports that name from `vue`, no second import is generated instead, because it is
the same binding.

Template transformation only supports Twig block tags (`{% block %}`, `{% endblock %}`, `{% parent %}`) and Twig comments. Templates containing Twig `{% extends '…' %}` fail the migration and must be handled manually before running the codemod.

## Migration outcomes

Each component is classified into one of three states:

| Status               | Meaning                                                                                     | Output                                         |
| -------------------- | ------------------------------------------------------------------------------------------- | ---------------------------------------------- |
| `fully-migrated`     | Native `<script setup>` with a `swDefinePublic({ … })` marker                               | `.vue` file written                            |
| `partially-migrated` | Native `<script setup>`, but parts were emitted as `TODO` comments                          | `.vue` file written, manual follow-up required |
| `not-migratable`     | Blocker found (`render()`, mixins, `Shopware.Component.extend()`, unsupported `inject`)      | No file written                                |

Every `.vue` file is a native setup component, so a component that cannot be
converted to `<script setup>` produces no file at all: a plain `<script>` SFC is
rejected by the build. Those components are reported with their blockers so they
can be prepared by hand and migrated on a second run.

## Programmatic API

```ts
import { mergeComponentFiles } from './generate-sfc';

const result = mergeComponentFiles(twigContent, jsContent);

if (result.status === 'fully-migrated') {
    fs.writeFileSync('my-component.vue', result.sfc);
}

// result.blockers — list of detected blockers (e.g. ['mixins', 'extends (parent: sw-button)'])
```

`result.sfc` is valid but unformatted: the emitters produce correct code and leave
layout to prettier. The CLI formats every file it writes with the Administration
prettier config, so programmatic callers should either do the same or run
`composer format:admin:fix` on the generated files.

## ⚠ Destructive Operations

`--delete-originals` is **irreversible**. It replaces `index.js` with a generated
entry point that imports the new `.vue` file, and deletes `.html.twig` for every
**fully-migrated** component. Partially-migrated components keep their originals,
because their `TODO` comments must be resolved before the entry point may switch.

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

- **`rewrite target '<name>' is shadowed by a local binding`** — the member reads `this.<name>` (or a
  prop, which is rewritten through `props`) at a place where a parameter or local of that name is in
  scope, so the rewritten bare identifier would read the local instead of the setup binding. The
  member is dropped rather than mis-rewritten: rename the local binding and migrate again.

- **`dropped member '<name>'`** — the reported member or hook was dropped because a member it uses
  was dropped first. Migrate the named member by hand and re-run the codemod; the reference usually
  converts on its own afterwards. `unknown this property '<name>'` means the opposite: the codemod
  saw no declaration it could parse — a mixin, a plugin, a global helper, or an option entry whose
  shape it does not support (a `...mapPageErrors(…)` computed spread, for example).

- **Mixins and `Shopware.Component.extend()`** — must be manually inlined
- **Render functions** — must be rewritten as templates by hand

## Manual migration: `extends`-based components

Components registered via `Shopware.Component.extend()` cannot be converted, because their parent's options are not part of the source file. The migration report shows a `⚠` warning line with the parent component name:

```
✗  not-migratable      [extends (parent: sw-button)]  sw-extended-button/index.js
   ⚠  manually inline parent options from 'sw-button' before re-running codemod; see README.md
```

Automatic inlining is out of scope for this codemod because it requires resolving and deep-merging the parent's implementation, which has too many edge cases (chained inheritance, circular references, parents that are themselves not migratable).

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

4. **Re-run the codemod** — the component should now be classified as `fully-migrated`
   (unless other blockers remain).

    ```bash
    npm run codemod:sfc-migration -- --write path/to/sw-extended-button
    ```

## Known Limitations

The following Options API features are **not automatically converted**. After migration,
search your codebase for the `TODO:` comments the codemod inserts, and resolve each one manually.

| Feature                       | Behavior                                    | How to fix                                              |
| ----------------------------- | ------------------------------------------- | ------------------------------------------------------- |
| `provide` (unsupported shape) | Drops the whole option with a TODO comment  | Add `provide(key, value)` calls manually in setup       |
| `expose` (unsupported shape or an entry that was not migrated) | Drops the whole option with a TODO comment | Add a `defineExpose({ … })` call manually in setup |
| `components`                  | Drops silently                              | Verify components are globally registered; remove if so |
| `directives`                  | Drops with TODO comment                     | Register directives globally or inline in setup         |
| `beforeCreate`                | Drops with TODO comment                     | Move logic to top of `<script setup>`                   |
| `this.$store`                 | Inserts TODO comment                        | Migrate Vuex access to a composable                     |
| A member named like a generated import (`ref`, `computed`, `watch`, `onMounted`, `useRouter`, …) | Drops the member with a TODO comment | Rename the member, then migrate again |
| A `this.<member>` access shadowed by a local of the rewritten name | Drops the member with a TODO comment | Rename the local binding, then migrate again |
| `this.$parent` / `this.$root` | Inserts TODO comment                        | Refactor to avoid parent traversal                      |
| Watch path with an undeclared root or a non-identifier segment | Leaves a TODO comment and skips the watcher | Write watcher manually                 |
