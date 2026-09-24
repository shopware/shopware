# Native Setup Authoring

Native setup is the Administration term for Vue's native `<script setup>` SFC syntax. Shopware setup authoring is native setup plus a filename-based base/override convention for the Composition-API extension system. It is *not* plain native setup: the transform lowers the author body into Shopware's base/override contracts before Vue compiles the SFC. The runtime side of those contracts is described in [04-composition-extension-system.md](./04-composition-extension-system.md).

## Modes

Mode and component name come from the filename; the name is the public override target.

- **Base** — a plain `.vue` file. `sw-my-component.vue` and `sw-my-component/index.vue` both resolve to `sw-my-component`.
- **Override** — an `.override.vue` file. `sw-my-component.override.vue` and `sw-my-component/index.override.vue` both resolve to `sw-my-component`.

There is no third mode: **every** `.vue` file is a native setup component, so every one of them needs a `<script setup>` block with its marker. A plain `<script>` (Options API) or a template-only `.vue` is rejected at build time rather than compiled as an ordinary Vue SFC — see [Rejected loudly](#rejected-loudly).

```vue
<!-- sw-my-component.vue (base) -->
<script setup lang="ts">
import { ref } from 'vue';

const props = defineProps<{ initialCount?: number }>();
const count = ref(props.initialCount ?? 0);
const internalValue = ref('private');

swDefinePublic({ count });
</script>
```

```vue
<!-- sw-my-component.override.vue (override) -->
<script setup lang="ts">
import { computed } from 'vue';

const previousState = useSwPreviousState<'sw-my-component'>();
const count = computed(() => previousState.count.value * 2);

swDefineOverride({ count });
</script>
```

## Runtime lowering

The transform runs before Vue compiles the SFC. Generated code talks only to the versioned runtime `globalThis.Shopware.Component.__setupRuntime.v1`, so a built extension bundle keeps working when the runtime changes. Every generated binding starts with `__swSetup`.

### Base

The author body stays a native `<script setup>` — macros in place, nothing hoisted or wrapped. The transform:

1. renames each top-level runtime binding to `__swSetupAuthor_<name>`;
2. rewrites references that run after setup to `__swSetupLate.<name>` (see [Late binding](#late-binding));
3. appends a footer that re-declares the original names from `attach()`, which applies all overrides, followed by a generated `defineExpose()` with the component's props and its `swDefinePublic()` entries (see [swDefinePublic() is also the parent-facing surface](#swdefinepublic-is-also-the-parent-facing-surface));
4. adds `:data="$dataScope"` to every `<sw-block name="...">`, so block overrides render with the component's state.

```ts
// sw-demo.vue, lowered (abridged)
const __swSetupRuntime = globalThis.Shopware.Component.__setupRuntime.v1;
const __swSetupLate = __swSetupRuntime.late({ persist: () => __swSetupAuthor_persist });
const __swSetupAuthor_count = ref(0);
function __swSetupAuthor_save() { __swSetupLate.persist(); }
function __swSetupAuthor_persist() { /* ... */ }

const { count, save, persist } = __swSetupRuntime.attach({
    name: 'sw-demo',
    public: { count: __swSetupAuthor_count, persist: __swSetupAuthor_persist },
    private: { save: __swSetupAuthor_save },
    late: __swSetupLate,
});

defineExpose({ ...__swSetupRuntime.expose(), count, persist });
```

Base mode is **auto-private**: every top-level runtime binding becomes private state unless it is listed in `swDefinePublic({...})`, which every base component must declare (see [Setup markers](#setup-markers)). Private state is still normal template state — it is only hidden from the top-level public override API. Overrides reach it through `useSwPreviousState()._private`. Macro-derived bindings are treated the same way: `const props = defineProps(...)`, `const emit = defineEmits(...)`, and `const slots = defineSlots(...)` become private state under their declared names, so templates can reference `emit`, `slots`, and `props.<name>` directly.

**Top-level `let` and `var` are rejected.** The footer copies every binding into the component's state once, at the end of setup, so a later reassignment in the body would never reach the template, a parent or an override. Declare bindings with `const` and keep mutable state in a ref (`const count = ref(0)`).

The `data` binding and the default slot scope of `<sw-block>` are owned by the transform: authoring `data`, `#default`, or a `v-bind` object on `<sw-block>` is rejected.

### Override

The author's `<script setup>` becomes a plain `<script>` that registers the body as an override callback **at module scope**, so the override is registered as soon as its module is imported. An extension's entry file imports every `*.override.vue` of the extension automatically. Imports, ambient `declare` statements and type exports are hoisted above the callback, because they cannot live in a function. A generated, comment-only `<script setup>` follows the plain script: without it Vue would not expose the module-scope bindings to the template.

```vue
<!-- sw-demo.override.vue, lowered (abridged) -->
<template>
    <sw-block extends="sw-demo-content" #default="{ __swOverride: { [__swSetupNamespace]: { doubled, previousState } = {} } = {}, persist }">
        <span>{{ doubled }}</span>
    </sw-block>
</template>

<script lang="ts">
import { computed } from 'vue';

const __swSetupNamespace = Symbol('sw-demo.override');
globalThis.Shopware.Component.__setupRuntime.v1.override('sw-demo', '1a847b39', (__swSetupPreviousState, __swSetupProps, __swSetupContext) => {
const useSwPreviousState = () => __swSetupPreviousState;
const useSwProps = () => __swSetupProps;
const useSwContext = () => __swSetupContext;

const previousState = useSwPreviousState();
const doubled = computed(() => previousState.count.value * 2);
function persist() { /* ... */ }

return { persist, __swOverride: { [__swSetupNamespace]: { doubled, previousState } } };
});
</script>
<script setup lang="ts">/* exposes the module-scope bindings to the template */</script>
```

The second argument of `override()` is a file key: a short SHA-256 hash of the file's path relative to the working directory. Registering the same key again replaces the entry in place, which is what a hot reload does.

Override mode requires exactly one top-level `swDefineOverride({...})`; only the bindings listed there replace base state. The callback runs once per base component instance, during that instance's setup.

**`<sw-block extends>` content.** The override component is also mounted once, hidden, in `sw-admin`; that mount is what registers its `<sw-block extends>` content as a block layer (see [04-native-block-system.md](./04-native-block-system.md)). A template-less override receives a generated comment-only template so the hidden mount does not warn. The content can reference every binding of the override:

- bindings listed in `swDefineOverride()` by their name, read from the rendering component's state;
- every other override-local binding — including `useSw*` aliases such as `previousState` — through the reserved `__swOverride` slot-scope channel, keyed by a module-scope `Symbol()`.

The `= {}` defaults in the generated slot scope keep a host without override-local state (an Options API component, a nested Twig block) from failing; the bindings are `undefined` there.

Forwarded override bindings are **read-only** in the template: they arrive as slot-scope locals, so a template write (`@click="count = count + 1"`, `count++`) would assign to the local and take no effect. The transform rejects such writes at build time. Mutate override state from a function defined in the override setup and call that function instead.

**Runtime inputs** are explicit:

- Base: `defineProps(...)`, `withDefaults(defineProps(...), ...)`, plus Vue's own composables (`useAttrs()`, `useSlots()`, …) — the body is native `<script setup>`.
- Override: `useSwPreviousState()`, `useSwProps()`, `useSwContext()` — transform-injected local helpers, override mode only.

## Late binding

A function in a base component calls the **override-aware** binding, not its own original. The transform rewrites every value reference to a top-level binding that sits inside a function (a function declaration, an arrow, a `computed()` getter, a handler) to `__swSetupLate.<name>`. Until `attach()` runs at the end of setup, `__swSetupLate.<name>` returns the component's own binding; afterwards it returns the final binding with every override applied, in its original shape (a ref stays a ref, a function stays a function).

```ts
// sw-demo.vue
function persist(value: number) { /* base implementation */ }
function save() { persist(count.value); }   // calls the override's persist() if one replaced it

swDefinePublic({ count, persist });
```

A reference that runs **during setup** keeps the component's own binding, because overrides are applied only at the end of setup:

```ts
const doubled = computed(() => count.value * 2);   // deferred: reads the override-aware count
watch(count, log);                                  // setup time: watches the base's own ref
```

Not late-bound:

- bindings initialized by a Vue macro (`defineProps`, `withDefaults`, `defineEmits`, `defineSlots`), and every reference inside a Vue macro argument, which Vue hoists out of setup;
- imports, which are never setup state;
- type positions (`typeof x`, type references, signature parameters) and export specifiers.

## Props

Base components declare props with Vue's native `defineProps(...)` or `withDefaults(defineProps(...), ...)`. The macro stays where you wrote it and Vue compiles it, so prop defaults, reactive destructuring, and `withDefaults` behave exactly as in any Vue 3.5 component. Read props through the props object (`props.count`) or a reactive destructure so access stays reactive, and keep defaults on the prop (a destructure default or `withDefaults`) rather than on a separate local, because the template reads the prop and not the local.

**One Shopware-specific rule:** a top-level setup binding must not share a declared prop's name.

```ts
const props = defineProps<{ count: number }>();
const count = ref(0);   // collides with the declared prop `count`
```

Native Vue lets the setup binding shadow the prop in the template; the extension runtime cannot keep both apart, because overrides receive props and state separately. In development the runtime throws when the component is set up, naming the colliding bindings. Rename the local and read the prop through `props.count`.

The [`vue/no-dupe-keys` ESLint rule](#editor-integration) reports the collision earlier, in your editor and in `composer eslint:admin`, for an inline object literal and a type **declared in the same file**:

```ts
interface Props { count: number }
const props = defineProps<Props>();   // vue/no-dupe-keys flags the `count` collision below
const count = ref(0);
```

**It does not cover an imported prop type**, because nothing resolves across files there. That collision only surfaces as the development error at runtime:

```ts
import type { Props } from './props.types';   // export interface Props { count: number }

const props = defineProps<Props>();
const count = ref(0);                         // no lint error, throws in development
```

## Setup markers

`swDefinePublic({...})` (base) marks the public override API; `swDefineOverride({...})` (override) marks the override payload. Both are called once, as a statement at the top level, and accept **only shorthand bindings**, so a key always equals its local binding name:

```text
swDefinePublic({ count });
swDefineOverride({});
swDefineOverride({ count });
```

Renaming, string keys, computed keys, spreads, and non-object-literal arguments are rejected: the transform, lint, and type layers need a stable compile-time key, and a renamed key could silently shadow another binding. Imported bindings cannot be listed.

**Both markers are mandatory in their mode** — pass an empty object when there is nothing to declare (`swDefinePublic({})` for a base component with no public state, `swDefineOverride({})` for a template-only override). A transformed base component is an extension point: its filename becomes the public override target and its bindings become overrideable state. Requiring the marker keeps that from happening merely because a file carries a `<script setup>` block, and it tells a reader at a glance that the file is lowered rather than being a plain Vue SFC.

An override key the base component does not provide logs a development warning when the override is applied.

### swDefinePublic() is also the parent-facing surface

`swDefinePublic({...})` declares one surface, used in two directions. Besides marking what an override may replace, the transform generates a `defineExpose()` call from the same entries, so a parent holding a template ref reads and writes exactly those bindings:

```text
// sw-tree-item.vue
const opened = ref(false);
function openTreeItem() { opened.value = true; }

swDefinePublic({ opened, openTreeItem });
```

```text
// a consumer
const item = useTemplateRef('treeItem');
item.value.openTreeItem();
item.value.opened = false;      // writes through to the component's own ref
```

**Authoring `defineExpose()` yourself is rejected** — there is one declaration, and the transform owns the call. Vue allows a single `defineExpose()` per block, and a second, hand-written one could disagree with what overrides see.

**Props ride along, and you never declare them.** The generated call spreads the component's own props in front of the public bindings, so `ref.value.label` keeps working after a component is lowered. They are read-only — a prop belongs to the parent that passes it, so writing one through the ref warns and changes nothing.

The one consequence to know: **a binding you left out of `swDefinePublic()` is invisible to a parent, not only to overrides.** It reads as `undefined` through a template ref. Add it to the marker if a parent needs it.

Reaching in through Vue internals — `vnode.component.proxy`, or walking `subTree.children` to find an instance — is not covered by any of this and never was. A `<script setup>` component hides its setup state from the instance proxy, and `defineExpose()` does not change what that proxy returns. Use a template ref.

## Types

The macros and helpers are declared globally in `build/vue-setup-transform/shopware-setup-macros.d.ts`; extensions get them through `composer admin:setup-extension-tooling`.

- `swDefinePublic<T>(bindings: T): T` returns its argument's type.
- `useSwPreviousState<'sw-my-component'>()` types the previous state through `ComponentPublicApiMapping['sw-my-component']`. A name the mapping does not know gives `Record<PropertyKey, any>`; you can also pass the state shape itself, `useSwPreviousState<{ count: Ref<number> }>()`.
- `useSwProps<T>()` and `useSwContext<T>()` default to a loose record and Vue's `SetupContext`.

Augment `ComponentPublicApiMapping` for a base component to type its overrides:

```ts
declare global {
    interface ComponentPublicApiMapping {
        'sw-my-component': { count: Ref<number> };
    }
}
```

## Differences from native setup

- Base public/private state is explicit Shopware extension state, not native setup-return behaviour.
- Override SFCs register their callback at module scope when they are imported, not when a component mounts.
- **Base mode does not touch the Vue macros at all.** `defineProps`, `withDefaults`, `defineEmits`, `defineSlots`, and `defineOptions` stay where you wrote them and are compiled by Vue with their normal semantics — including Vue's own rules on how many times each may appear, and Vue's own diagnostics for macro arguments it cannot hoist. The transform renames top-level bindings, rewrites deferred references, and adds the header and footer.
- **Base functions see overrides.** A deferred reference to a top-level binding resolves to the override-aware binding (see [Late binding](#late-binding)).
- **Override mode moves the author body into a callback**, so imports and type-only declarations (`interface`, `type`, ambient `declare`) stay at the module root. Ambient `declare` statements describe values provided elsewhere and are never returned as setup state.
- **Forwarded override bindings are read-only in the template** (see [Override](#override)).
- Vue macros other than the base-mode set above are unsupported in either mode.
- Top-level `await` and top-level `let`/`var` bindings in a base component are unsupported.

## Rejected loudly

The transform rejects these at build time:

- Script languages other than `js`, `jsx`, `ts`, and `tsx`
- An SFC without a `<script setup>` block — a plain `<script>` (Options API) or a template-only `.vue` file. Every `.vue` component is extendable, and the markers that declare that only exist in `<script setup>`, so such a file would compile into a component nothing can override
- Additional `<script>` blocks next to the Shopware setup block
- `defineModel()` and `defineExpose()`, in either mode — the transform generates `defineExpose()` from `swDefinePublic({...})`
- Base-mode macros used in override mode (`defineProps`, `withDefaults`, `defineEmits`, `defineSlots`, `defineOptions`)
- Override-only helpers (`useSwPreviousState()`, `useSwProps()`, `useSwContext()`) in base mode, at any depth
- Top-level `await`
- Top-level `let` or `var` bindings in a base component
- ES module exports in an override component (type exports are allowed)
- Duplicate top-level binding names
- Non-top-level, duplicate, assigned, spread, renamed/string/computed-key, or non-object-literal `swDefinePublic()` / `swDefineOverride()` usage, and marker entries that are imported or not declared
- A missing marker: no `swDefinePublic()` in a base component, or no `swDefineOverride()` in an override
- On `<sw-block>`: any attribute other than a static `name` (base) or `extends` (override), including authored `data`, `#default`, or `v-bind`; a direct non-default named slot below it
- Top-level markup other than `<sw-block extends>` in an override template
- Template writes to a forwarded override binding inside `<sw-block extends>` content
- Reserved top-level binding names, declared or imported:
  - the `__swSetup` prefix, used for the transform's generated bindings
  - `__swOverride`, the reserved override-local slot-scope key
  - the macro and helper names (`swDefinePublic`, `swDefineOverride`, `useSwPreviousState`, `useSwProps`, `useSwContext`, and the Vue macros, which may only be imported from `vue`)

Malformed or unclosed SFC sections are left to Vue's compiler parser: if `@vue/compiler-sfc` reports SFC parse errors, the preprocessor skips transformation so Vue can present the primary parse error first.

## Parser behaviour

All parser-sensitive behaviour lives in `build/vue-setup-transform` (see its `README.md` for the code map). SFC block detection uses `@vue/compiler-sfc` and reads `descriptor.scriptSetup`; mode and component name come from the normalized filename. Script analysis uses Babel's scope tracking, so shadowed names are left alone. A missing `<script setup>` block is an error rather than an opt-out, so the only `.vue` file the transform hands back untouched is one Vue's own parser already rejected. Vue's parser deliberately treats fake `<script setup>` text inside comments, templates, styles, and script bodies as non-top-level content.

## Editor integration

Every transform rejection above surfaces in your editor, not only at build time. The `valid-shopware-setup` ESLint rule (`eslint-rules/core-rules`) runs the transform's validation (`analyzeShopwareSetupSfc`) against the file and reports its errors on the offending line — so a reserved binding name, a renamed marker key, or a wrong-mode macro is flagged as you type. There is one validator; the build enforces it and this rule mirrors it into the editor.

The prop/binding name collision is the one detection that is **not** a transform rejection: it is caught by the standard `vue/no-dupe-keys` ESLint rule instead, and by the development error at runtime (see [Props](#props)).
