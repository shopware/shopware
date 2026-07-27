# Native Setup Authoring

Native setup is the Administration term for Vue's native `<script setup>` SFC syntax. Shopware setup SFC authoring uses native setup plus filename-based base/override conventions for the Composition API extension system. It is not plain native setup semantics, because the body is lowered into Shopware's base/override callback contracts before Vue compiles the SFC.

## Supported Modes

Base components use a normal `.vue` filename. The component name is inferred from the filename:

- `sw-my-component.vue` -> `sw-my-component`
- `sw-my-component/index.vue` -> `sw-my-component`

```vue
<script setup lang="ts">
import { ref } from 'vue';

const props = defineProps<{
    initialCount?: number;
}>();
const count = ref(props.initialCount ?? 0);
const internalValue = ref('private');

swDefinePublic({
    count,
});
</script>
```

Overrides use an `.override.vue` filename. The overridden component name is inferred from the filename:

- `sw-my-component.override.vue` -> `sw-my-component`
- `sw-my-component/index.override.vue` -> `sw-my-component`

```vue
<script setup lang="ts">
import { computed } from 'vue';

const previousState = useSwPreviousState();
const doubled = computed(() => previousState.count.value * 2);

swDefineOverride({
    doubled,
});
</script>
```

## Props And Defaults

Base components declare props with Vue's native `defineProps(...)` or `withDefaults(defineProps(...), ...)` macros. The macro stays exactly where you wrote it: the author body runs as plain `<script setup>`, so Vue compiles the props declaration itself and the transform never moves or rewrites it. Override callbacks read the props object from the component instance at runtime, so nothing has to be threaded through the generated footer.

Read props through the props variable so access stays reactive:

```vue
<script setup lang="ts">
const props = defineProps<{ initialCount?: number }>();
const count = ref(props.initialCount ?? 0);
</script>
```

### Destructuring the props macro

```ts
const { initialCount = 0 } = defineProps<{ initialCount?: number }>();
```

This works, and it behaves exactly as it does in any other Vue 3.5 component — because the transform leaves it alone. A destructured props macro is not renamed and not returned as setup state, so Vue's own compiler sees it in macro position and applies its reactive-props-destructure rewrite: every reference becomes `props.initialCount`, and the `= 0` default is compiled into the prop declaration itself. The default therefore also reaches the template, because it lands on the prop and not on a local binding.

Destructuring `withDefaults(...)` is likewise left to Vue. It compiles, and Vue emits its own advice — *"withDefaults() is unnecessary when using destructure with defineProps(); reactive destructure will be disabled"* — which is Vue's diagnostic to give, not ours. Prefer a destructure default or a `props` variable.

### Defaults must live on the prop, not on a local binding

The value a template reads is the declared prop. A destructure default (above) and `withDefaults(...)` both compile onto the prop declaration, so both are fine. What does *not* work is computing a fallback into a separate local:

```ts
// the template still reads the prop, which has no default
const props = defineProps<{ initialCount?: number }>();
const initialCount = props.initialCount ?? 0;
```

With a `props` variable, use `withDefaults(...)`:

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

`initialCount` is a declared prop, so the template resolves `{{ initialCount }}` to the reactive prop. `withDefaults` bakes the default into the prop declaration, so the default is applied to the prop itself and is visible both in the setup body (`props.initialCount`) and in the template — reactively, and before any override merges run. A default written only on a separate local binding (`const initialCount = props.initialCount ?? 0`) never reaches the template, because the template reads the prop and not the local binding.

## Runtime Lowering

The preprocessor runs before Vue compiles the SFC. A base component keeps its author body exactly as written — plain `<script setup>`, macros in place, nothing hoisted and nothing wrapped. The transform only renames each top-level runtime binding to a reserved `__swSetupAuthor_<name>` alias and appends a generated `attachOverrides(...)` footer that re-declares the original names from the override wrapper, so templates read overrideable state exactly as before while the body text itself never moves. Overrides stay `<script setup>` components whose body registers an `overrideComponentSetup(...)` callback; each override component is rendered once in a hidden container at boot, which runs the registration and lets `<sw-block extends>` template content register its block overrides. A template-less override receives a generated comment-only template so the hidden component can mount without a missing-render warning.

Base mode is auto-private by default. Supported top-level local runtime bindings become private state unless they are listed in `swDefinePublic({...})`. Public state is the public override API surface. Private state is still normal component/template state; it is only hidden from the top-level public override API and remains available to overrides through `_private`.

Macro-derived bindings are treated the same way: `const props = defineProps(...)`, `const emit = defineEmits(...)`, and `const slots = defineSlots(...)` become private state under their declared names, so the template can reference `emit`, `slots`, and `props.<name>` directly. Generated internal bindings use a reserved `__swSetup` prefix, which is why top-level author bindings may not use it.

Base mode also adds `:data="$dataScope"` to every `<sw-block name="...">`. This forwards the generated script setup data scope to block overrides without requiring every base block author to write it manually. The `data` binding and the default slot scope of `<sw-block>` are owned by the transform: authoring `data`, `#default`, or a `v-bind` object on `<sw-block>` is rejected.

Override mode requires `swDefineOverride({...})`. Only bindings listed there replace base state. Override-local bindings are returned under deterministic private aliases only when they are referenced inside `<sw-block extends>` template content, and the transform exposes those aliases through the generated default slot scope of the extended block.

Override template bindings are read-only. Inside `<sw-block extends>` content, forwarded setup bindings arrive through the generated slot scope, so a template write to one would assign to a slot-scope local rather than the override's own reactive state and take no effect — the same line works in a base component, which makes it a silent trap. The transform therefore rejects assignment and update expressions targeting a forwarded binding (`@click="count = count + 1"`, `count++`) at build time. Mutate override state from a handler defined in the override setup and call that handler instead.

Runtime inputs are explicit. Base component props use Vue's native `defineProps(...)` or `withDefaults(defineProps(...), ...)` macros. Override props use a helper because override files cannot declare the base component's props with `defineProps(...)`.

- Base: `defineProps(...)`, `withDefaults(defineProps(...), ...)` — plus Vue's own composables (`useAttrs()`, `useSlots()`, …), since a base body is a native `<script setup>`
- Override: `useSwPreviousState()`, `useSwProps()`, `useSwContext()`

The `useSw...` calls are transform-injected local helpers in **override** mode only, not broad runtime globals. Base components get no injected helpers at all: their body runs natively, so Vue's own APIs apply unchanged.

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

`swDefineOverride({...})` is the override payload marker in override mode.

Supported:

```text
swDefineOverride({});
swDefineOverride({ count });
```

Rejected:

```ts
swDefineOverride({ count: localCount });
swDefineOverride({ 'count': localCount });
swDefineOverride({ [dynamicKey]: count });
swDefineOverride({ ...state });
swDefineOverride(state);
```

Like `swDefinePublic()`, only shorthand bindings are supported.

Override mode requires exactly one top-level `swDefineOverride({...})` call. Template-only overrides use `swDefineOverride({})`.

## Not Plain Native Setup Semantics

Shopware setup blocks differ from plain native setup:

- Base public/private state is explicit Shopware extension state, not native setup return behavior.
- Override SFCs register with `overrideComponentSetup(...)` at import time.
- In **base** mode the Vue macros are not touched at all. `defineProps(...)`, `withDefaults(...)`, `defineEmits(...)`, `defineSlots(...)`, `defineExpose(...)`, and `defineOptions(...)` stay where you wrote them and are compiled by Vue with their normal semantics — including Vue's own rules on how many times each may appear. The transform only renames top-level runtime bindings to `__swSetupAuthor_<name>` and appends the `attachOverrides(...)` footer.
- In **override** mode the author body moves into a callback, so imports and type-only declarations (`interface`, `type`, ambient `declare`) are lifted back out to the generated script root — where an import or an ambient declaration is legal — matching how Vue keeps them at the module root. Ambient `declare` statements describe runtime values provided from elsewhere, so they are never returned as setup state.
- Vue macros other than the base-mode set above are not supported in either mode.
- Runtime arguments passed to `defineProps(...)`, `withDefaults(...)`, `defineEmits(...)`, and `defineOptions(...)` must use inline values, imports, or type-only declarations. Vue hoists macro arguments into the component options object, outside `setup()`, so they cannot reference locally declared variables. Vue itself only reports this for `defineEmits(...)`; for the other three it would silently emit an options object referencing a name that only exists inside `setup()`, so the transform rejects it at build time instead. For prop defaults use a destructure default or `withDefaults(defineProps(...), { ... })` so the default lives on the reactive prop.
- Top-level `await` is not supported.

## Unsupported

The transform rejects these cases loudly:

- Script languages other than `js`, `jsx`, `ts`, and `tsx`
- `defineModel()`, in either mode
- Any of the base-mode macros used in override mode: `defineProps()`, `withDefaults()`, `defineEmits()`, `defineExpose()`, `defineSlots()`, `defineOptions()`
- Local setup bindings referenced in macro arguments passed to `defineProps(...)`, `withDefaults(...)`, `defineEmits(...)`, or `defineOptions(...)`
- Override-only helpers such as `useSwPreviousState()` and `useSwProps()` in base mode
- Top-level `await`
- Non-top-level, duplicate, spread, renamed/string/computed-key, or non-object-literal `swDefinePublic()` usage
- Missing, non-top-level, duplicate, spread, renamed/string/computed-key, or non-object-literal `swDefineOverride()` usage in override mode
- Authored `#default`, `data`, or `v-bind` bindings on `<sw-block>`, because the transform generates the block's slot scope and data bindings
- Top-level bindings using the reserved `__swSetup` prefix, which the transform uses for its generated bindings
- Additional `<script>` blocks next to Shopware setup blocks

Malformed or unclosed SFC sections are left to Vue's compiler parser. If `@vue/compiler-sfc` reports SFC parse errors, the Shopware setup preprocessor skips transformation so Vue can present the primary parse error first.

## Parser behavior

All parser-sensitive behavior lives in `build/vue-setup-transform`, with smaller helpers grouped in `build/vue-setup-transform/utils`. SFC block detection uses Vue's `@vue/compiler-sfc` parser first and reads `descriptor.scriptSetup`; mode and component name come from the normalized SFC filename. Plain `<script>` blocks are not candidates for transformation.

Vue's SFC parser deliberately treats fake `<script setup>` text inside HTML comments, templates, styles, and script bodies as non-top-level content. Malformed sections fail loudly instead of producing partial transforms.
