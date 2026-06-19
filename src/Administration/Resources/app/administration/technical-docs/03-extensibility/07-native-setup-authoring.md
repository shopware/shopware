# Native Setup Authoring

Native setup is the Administration term for Vue's native `<script setup>` SFC syntax. Shopware setup SFC authoring uses native setup plus explicit Shopware mode attributes for the Composition API extension system. It is not plain native setup semantics, because the body is lowered into Shopware's base/override callback contracts before Vue compiles the SFC.

## Supported Modes

Base components use `sw-component`:

```vue
<script setup lang="ts" sw-component="sw-my-component">
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

Overrides use `sw-override`:

```vue
<script setup lang="ts" sw-override="sw-my-component">
import { computed } from 'vue';

const previousState = useSwPreviousState();
const doubled = computed(() => previousState.count.value * 2);

swDefineOverride({
    doubled,
});
</script>
```

The two modes are mutually exclusive. Mode attributes must be static quoted strings. Bound attributes such as `:sw-component="name"` and `v-bind:sw-override="name"` are rejected.

## Runtime Lowering

The preprocessor runs before Vue compiles the SFC. Base components are lowered directly through `createExtendableSetup(...)`. Overrides are lowered to import-time `overrideComponentSetup(...)` registration so imported override SFC files register without needing to be mounted.

Base mode is auto-private by default. Supported top-level local runtime bindings become private state unless they are listed in `swDefinePublic({...})`. Public state is the public override API surface. Private state is still normal component/template state; it is only hidden from the top-level public override API and remains available to overrides through `_private`.

Base mode also adds `:data="$dataScope"` to every `<sw-block name="...">` that does not already declare `data`, `:data`, or `v-bind:data`. This forwards the generated script setup data scope to block overrides without requiring every base block author to write it manually.

Override mode requires `swDefineOverride({...})`. Only bindings listed there replace base state. Override-local bindings are returned under deterministic private aliases only when they are referenced inside `<sw-block extends>` template content, and the transform merges those aliases into the block's default slot scope.

Runtime inputs are explicit. Base component props use Vue's native `defineProps(...)` or `withDefaults(defineProps(...), ...)` macros. Override props use a helper because override files cannot declare the base component's props with `defineProps(...)`.

- Base: `defineProps(...)`, `withDefaults(defineProps(...), ...)`, `useSwContext()`
- Override: `useSwPreviousState()`, `useSwProps()`, `useSwContext()`

The `useSw...` calls are transform-injected local helpers, not broad runtime globals.

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

Shopware setup blocks differ from plain native setup in v1:

- Author code is lowered into Shopware base/override callback contracts.
- Base public/private state is explicit Shopware extension state, not native setup return behavior.
- Override SFCs register with `overrideComponentSetup(...)` at import time.
- Base components may use one props declaration macro, either `defineProps(...)` or `withDefaults(defineProps(...), ...)`; the declaration is hoisted once and original calls are replaced with the props object passed into the extendable setup runtime.
- Base components may use one `defineEmits(...)` declaration; the declaration is hoisted once and original calls are replaced with the setup context emitter.
- Base components may use one top-level `defineExpose(...)` call; it is replaced with the setup context expose function inside the extendable setup callback.
- Base components may use one `defineSlots(...)` declaration; the declaration is hoisted once and original calls are replaced with the setup context slots object.
- Base components may use one top-level `defineOptions(...)` call; it is kept at the generated script setup root and removed from the extendable setup callback.
- Runtime arguments passed to hoisted macros such as `defineProps(...)`, `withDefaults(...)`, `defineEmits(...)`, and `defineOptions(...)` must use inline values, imports, or type-only declarations. They must not reference local setup bindings, because those bindings live inside the generated Shopware setup callback. For local prop defaults, prefer destructured `defineProps()` defaults.
- Other Vue macros are not supported in Shopware setup blocks.
- Top-level `await` is not supported.

## Unsupported in v1

The transform rejects these cases loudly:

- Script languages other than `js`, `jsx`, `ts`, and `tsx`
- Bound `sw-component` or `sw-override` attributes
- Vue macros except supported base props, emits, expose, slots, and options declarations: `defineModel()`
- Props declaration macros in override mode, or more than one props declaration macro
- Local setup bindings referenced in hoisted macro arguments passed to `defineProps(...)`, `withDefaults(...)`, `defineEmits(...)`, or `defineOptions(...)`
- Override-only helpers such as `useSwPreviousState()` and `useSwProps()` in base mode
- `defineEmits()` in override mode, or more than one `defineEmits()` call
- `defineExpose()` in override mode, outside the top level, or more than one `defineExpose()` call
- `defineSlots()` in override mode, or more than one `defineSlots()` call
- `defineOptions()` in override mode, outside the top level, or more than one `defineOptions()` call
- Top-level `await`
- Non-top-level, duplicate, spread, renamed/string/computed-key, or non-object-literal `swDefinePublic()` usage
- Missing, non-top-level, duplicate, spread, renamed/string/computed-key, or non-object-literal `swDefineOverride()` usage in override mode
- Top-level TypeScript ambient `declare` declarations, because they are not runtime setup state
- Additional `<script>` blocks next to Shopware setup blocks

Malformed or unclosed SFC sections are left to Vue's compiler parser. If `@vue/compiler-sfc` reports SFC parse errors, the Shopware setup preprocessor skips transformation so Vue can present the primary parse error first.

## Parser behavior

All parser-sensitive behavior lives in `build/vue-setup-transform`, with smaller helpers grouped in `build/vue-setup-transform/utils`. SFC block detection uses Vue's `@vue/compiler-sfc` parser first and reads `descriptor.scriptSetup`, then the transform reads only the matched `<script setup>` start tag to preserve strict Shopware-specific attribute checks such as rejecting bound mode attributes. Plain `<script>` blocks are not candidates for transformation.

Vue's SFC parser deliberately treats fake `<script setup sw-component>` text inside HTML comments, templates, styles, and script bodies as non-top-level content. Malformed sections fail loudly instead of producing partial transforms.

## Biome and oxlint outlook

Direct Biome support is not part of v1. Biome's Vue support is still partial, so Shopware setup lowering should stay behind the shared preprocessor boundary until Biome has a stable SFC extension point.

Direct oxlint support is not part of v1. oxlint has JavaScript plugin support for ESLint-compatible rules, but that path is still documented as alpha. Keeping `valid-shopware-setup` as a focused ESLint-compatible rule and keeping parser logic outside the rule preserves the cleanest migration path.

## Proposals

Full macro support could be added by mapping more Vue macros to explicit Shopware equivalents. Props, emits, and slots declaration macros are currently supported for base components by hoisting one declaration each and replacing the original call inside the extendable setup callback. Expose declarations are replaced with the setup context expose function inside the callback. Options declarations are supported by preserving one top-level `defineOptions(...)` call in the generated script setup root.

Top-level await could be supported only if the extension runtime becomes async-first for both base setup and override application. Until that runtime contract changes, v1 keeps top-level setup synchronous.

## Discussion

Open design questions should be recorded here:

- Should string-literal public keys that are not valid JavaScript identifiers receive a documented template aliasing convention?
- Which future Volar plugin API version should be treated as the minimum supported editor integration target?
