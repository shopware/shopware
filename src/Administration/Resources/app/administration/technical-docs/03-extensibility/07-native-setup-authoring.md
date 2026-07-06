# Native Setup Authoring

Native setup is the Administration term for Vue's native `<script setup>` SFC syntax. Shopware setup SFC authoring uses native setup plus filename-based mode inference for the Composition API extension system. It is not plain native setup semantics, because the body is lowered into Shopware's base callback contract before Vue compiles the SFC.

> This transform handles base components. Override files (`*.override.vue`) are recognized and rejected with a clear error; override authoring is documented with the override transform.

## Supported Modes

Mode and component name are inferred from the SFC filename, not from attributes. Every `<script setup>` block is treated as a Shopware setup block.

Base components use a plain `.vue` filename. The component name is the filename without its `.vue` suffix, or the parent directory name when the file is `index.vue`:

- `sw-my-component.vue` -> base component `sw-my-component`
- `sw-my-component/index.vue` -> base component `sw-my-component`

```vue
<script setup lang="ts">
import { ref } from 'vue';

const props = withDefaults(defineProps<{
    initialCount?: number;
}>(), {
    initialCount: 0,
});
const count = ref(props.initialCount);
const internalValue = ref('private');

swDefinePublic({
    count,
});
</script>
```

## Props And Defaults

Base components declare props with Vue's native `defineProps(...)` or `withDefaults(defineProps(...), ...)` macros. The declaration is hoisted to the generated script setup root and the props object is passed into `createExtendableSetup(...)`. Inside the setup callback the original macro call is replaced with the reactive props object.

Read props through the props variable so access stays reactive:

```vue
<script setup lang="ts">
const props = defineProps<{ initialCount?: number }>();
const count = ref(props.initialCount ?? 0);
</script>
```

### Destructuring the props macro is not supported

```ts
// rejected — defaults declared here are not applied
const { initialCount = 0 } = defineProps<{ initialCount?: number }>();
```

Since Vue 3.5, destructuring `defineProps()` lets you declare prop defaults inline, and Vue's compiler keeps them reactive by rewriting every reference (`initialCount` -> `props.initialCount`) and folding the `= 0` default into the props declaration. The Shopware transform hoists `defineProps(...)` out of macro position before Vue sees it, so that rewrite never runs: a destructured binding would be a one-time snapshot of the props object (non-reactive), and because it is returned as setup state it collides with the declared prop key that `createExtendableSetup()` strips from returned state — leaving the template binding `undefined`. Reproducing Vue's behavior would mean maintaining our own reference-rewrite pass, which we deliberately avoid.

The transform therefore rejects destructuring the props macro. Destructuring `defineProps()` reports the default-specific message above, because inline defaults are the case Vue developers expect to work; destructuring `withDefaults(...)` — already a non-idiomatic pattern — reports a plain "read `props.<name>`" message. Assign the macro to a variable and read `props.<name>`, and use `withDefaults(defineProps(...), { ... })` for defaults.

### Defaults reach the template through the prop

The value a template reads is the declared prop, so a default must live on the prop declaration, not on a local binding. Use `withDefaults(...)`:

```vue
<template>
    <p>{{ initialCount }}</p>
</template>
<script setup lang="ts">
const props = withDefaults(defineProps<{ initialCount?: number }>(), {
    initialCount: 0,
});
</script>
```

`initialCount` is a declared prop, so the template resolves `{{ initialCount }}` to the reactive prop. `withDefaults` bakes the default into the prop declaration, so the default is applied to the prop itself and is visible both in the setup body (`props.initialCount`) and in the template — reactively, and before any override merges run. A default written only on a local binding (a destructured default, or `const initialCount = props.initialCount ?? 0`) never reaches the template, because the template reads the prop and not the local binding.

## Runtime Lowering

The preprocessor runs before Vue compiles the SFC. Base components are lowered directly through `createExtendableSetup(...)`.

Base mode is auto-private by default. Supported top-level local runtime bindings become private state unless they are listed in `swDefinePublic({...})`. Public state is the public override API surface. Private state is still normal component/template state; it is only hidden from the top-level public override API and remains available to overrides through `_private`.

Macro-derived bindings are treated the same way: `const props = defineProps(...)`, `const emit = defineEmits(...)`, and `const slots = defineSlots(...)` become private state under their declared names, so the template can reference `emit`, `slots`, and `props.<name>` directly. Generated internal bindings use a reserved `__swSetup` prefix, which is why top-level author bindings may not use it.

Base mode also adds `:data="$dataScope"` to every `<sw-block name="...">` that does not already declare `data`, `:data`, or `v-bind:data`. This forwards the generated script setup data scope to block overrides without requiring every base block author to write it manually.

Runtime inputs are explicit. Base component props use Vue's native `defineProps(...)` or `withDefaults(defineProps(...), ...)` macros, and `useSwContext()` reads the setup context.

The `useSwContext()` call is a transform-injected local helper, not a broad runtime global.

## Setup Macros

`swDefinePublic({...})` is the public marker in base mode.

Supported:

```text
swDefinePublic({ count });
```

Rejected:

```ts
swDefinePublic({ count: localCount });
swDefinePublic({ 'count': localCount });
swDefinePublic({ [dynamicKey]: count });
swDefinePublic({ ...state });
swDefinePublic(state);
```

Only shorthand bindings are supported, so a public key always equals its local binding name. Renaming, string keys, and computed keys are rejected: the transform, lint, and type layers need a stable compile-time key, and a renamed key could silently shadow another binding.

## Not Plain Native Setup Semantics

Shopware setup blocks differ from plain native setup:

- Author code is lowered into Shopware's base callback contract.
- Base public/private state is explicit Shopware extension state, not native setup return behavior.
- Base components may use one props declaration macro, either `defineProps(...)` or `withDefaults(defineProps(...), ...)`; the declaration is hoisted once and original calls are replaced with the props object passed into the extendable setup runtime.
- Destructuring the props declaration macro is not supported; assign it to a variable and read `props.<name>`.
- Base components may use one `defineEmits(...)` declaration; the declaration is hoisted once and original calls are replaced with the setup context emitter.
- Base components may use one top-level `defineExpose(...)` call; it is replaced with the setup context expose function inside the extendable setup callback.
- Base components may use one `defineSlots(...)` declaration; the declaration is hoisted once and original calls are replaced with the setup context slots object.
- Base components may use one top-level `defineOptions(...)` call; it is kept at the generated script setup root and removed from the extendable setup callback.
- Type-only declarations (`interface`, `type`, and ambient `declare` statements) are hoisted to the generated script setup root and removed from the setup callback, matching how Vue keeps them at the module root. Ambient `declare` statements describe runtime values provided from elsewhere, so they are never returned as setup state.
- Runtime arguments passed to hoisted macros such as `defineProps(...)`, `withDefaults(...)`, `defineEmits(...)`, and `defineOptions(...)` must use inline values, imports, or type-only declarations. They must not reference local setup bindings, because those bindings live inside the generated Shopware setup callback. For prop defaults use `withDefaults(defineProps(...), { ... })` so the default lives on the reactive prop.
- Other Vue macros are not supported in Shopware setup blocks.
- Top-level `await` is not supported.

## Unsupported

The transform rejects these cases loudly:

- Script languages other than `js`, `jsx`, `ts`, and `tsx`
- Vue macros except supported base props, emits, expose, slots, and options declarations: `defineModel()`
- More than one props declaration macro
- Destructured `defineProps()` or `withDefaults(defineProps(...), ...)`
- Local setup bindings referenced in hoisted macro arguments passed to `defineProps(...)`, `withDefaults(...)`, `defineEmits(...)`, or `defineOptions(...)`
- More than one `defineEmits()` call
- `defineExpose()` outside the top level, or more than one `defineExpose()` call
- More than one `defineSlots()` call
- `defineOptions()` outside the top level, or more than one `defineOptions()` call
- Top-level `await`
- Non-top-level, duplicate, spread, renamed/string/computed-key, or non-object-literal `swDefinePublic()` usage
- Top-level bindings using the reserved `__swSetup` prefix, which the transform uses for its generated bindings
- Additional `<script>` blocks next to Shopware setup blocks

Malformed or unclosed SFC sections are left to Vue's compiler parser. If `@vue/compiler-sfc` reports SFC parse errors, the Shopware setup preprocessor skips transformation so Vue can present the primary parse error first.

## Parser behavior

All parser-sensitive behavior lives in `build/vue-setup-transform`, with smaller helpers grouped in `build/vue-setup-transform/utils`. SFC block detection uses Vue's `@vue/compiler-sfc` parser and reads `descriptor.scriptSetup`; the mode (`base`/`override`) and component name are then derived from the filename, not from block attributes. A Shopware setup block combined with a sibling plain `<script>` block fails loudly. Plain `<script>` blocks on their own are not candidates for transformation.

Vue's SFC parser deliberately treats `<script setup>` text inside HTML comments, templates, styles, and script bodies as non-top-level content. Malformed sections fail loudly instead of producing partial transforms.

## Biome and oxlint outlook

Direct Biome support is not included. Biome's Vue support is still partial, so Shopware setup lowering stays behind the shared preprocessor boundary until Biome has a stable SFC extension point.

Direct oxlint support is not included. oxlint has JavaScript plugin support for ESLint-compatible rules, but that path is still documented as alpha. Keeping `valid-shopware-setup` as a focused ESLint-compatible rule and keeping parser logic outside the rule keeps that integration straightforward.

## Proposals

Full macro support could be added by mapping more Vue macros to explicit Shopware equivalents. Props, emits, and slots declaration macros are currently supported for base components by hoisting one declaration each and replacing the original call inside the extendable setup callback. Expose declarations are replaced with the setup context expose function inside the callback. Options declarations are supported by preserving one top-level `defineOptions(...)` call in the generated script setup root.

Reactive props destructure (`const { count = 0 } = defineProps()`) could be supported by mirroring `@vue/compiler-sfc`'s `propsDestructure` transform: fold each destructure default into the hoisted props declaration, rewrite every reference to the destructured binding to `props.<name>` (scope-aware), and stop returning those names as setup state. That would require maintaining a reference-rewrite pass, so it is deliberately left out; the transform rejects destructured props macros instead.

Top-level await could be supported only if the extension runtime becomes async-first for both base setup and override application. Until that runtime contract changes, top-level setup stays synchronous.

## Discussion

Open design questions should be recorded here:

- Should string-literal public keys that are not valid JavaScript identifiers receive a documented template aliasing convention?
- Which future Volar plugin API version should be treated as the minimum supported editor integration target?
