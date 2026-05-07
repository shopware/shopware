# Shopware Setup SFC Authoring

Shopware setup SFC authoring uses native Vue `<script setup>` plus explicit Shopware mode attributes for the Composition API extension system. It is not plain native Vue `<script setup>` semantics, because the body is lowered into Shopware's base/override callback contracts before Vue compiles the SFC.

## Supported Modes

Base components use `sw-component`:

```vue
<script setup lang="ts" sw-component="sw-my-component">
import { ref } from 'vue';

const props = useSwProps();
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
</script>
```

The two modes are mutually exclusive. Mode attributes must be static quoted strings. Bound attributes such as `:sw-component="name"` and `v-bind:sw-override="name"` are rejected.

## Runtime Lowering

The preprocessor runs before Vue compiles the SFC. Base components are lowered through `createScriptSetupExtendableComponent()`, which is a thin bridge to `createExtendableSetup(...)`. Overrides are lowered to import-time `overrideComponentSetup(...)` registration so imported override SFC files register without needing to be mounted.

Base mode is auto-private by default. Supported top-level local runtime bindings become private state unless they are listed in `swDefinePublic({...})`. Public state is the public override API surface. Private state is still normal component/template state; it is only hidden from the top-level public override API and remains available to overrides through `_private`.

Override mode returns supported top-level local runtime bindings as the override payload. Imports stay as imports and are never returned automatically.

Runtime inputs are explicit composable-style accessors:

- Base: `useSwProps()`, `useSwContext()`
- Override: `useSwPreviousState()`, `useSwProps()`, `useSwContext()`

These are transform-injected local helpers, not broad runtime globals.

## Public Marker

`swDefinePublic({...})` is the only public marker in base mode.

Supported:

```text
swDefinePublic({ count });
swDefinePublic({ count: localCount });
swDefinePublic({ 'count': localCount });
```

Rejected:

```ts
swDefinePublic({ [dynamicKey]: count });
swDefinePublic({ ...state });
swDefinePublic(state);
```

Computed public keys are intentionally unsupported because transform, lint, and type layers need a stable compile-time key.

## Not Native Setup Semantics

Shopware setup blocks differ from native Vue `<script setup>` in v1:

- Author code is lowered into Shopware base/override callback contracts.
- Base public/private state is explicit Shopware extension state, not native setup return behavior.
- Override SFCs register with `overrideComponentSetup(...)` at import time.
- `defineProps()` and other Vue macros are not supported in Shopware setup blocks.
- Top-level `await` is not supported.

## Unsupported in v1

The transform rejects these cases loudly:

- Script languages other than `js`, `jsx`, `ts`, and `tsx`
- Bound `sw-component` or `sw-override` attributes
- Vue macros: `defineProps()`, `defineEmits()`, `defineExpose()`, `defineOptions()`, `defineSlots()`, `defineModel()`, `withDefaults()`
- Top-level `await`
- Non-top-level, duplicate, spread, computed-key, or non-object-literal `swDefinePublic()` usage
- Unsupported top-level declaration shapes such as destructuring declarations
- Additional `<script>` blocks next to Shopware setup blocks

Malformed or unclosed SFC sections are left to Vue's compiler parser. If `@vue/compiler-sfc` reports SFC parse errors, the Shopware setup preprocessor skips transformation so Vue can present the primary parse error first.

## Tooling

All parser-sensitive behavior lives in `build/vue-setup-transform`, with smaller helpers grouped in `build/vue-setup-transform/utils`. SFC block detection uses Vue's `@vue/compiler-sfc` parser first and reads `descriptor.scriptSetup`, then the transform reads only the matched `<script setup>` start tag to preserve strict Shopware-specific attribute checks such as rejecting bound mode attributes. Plain `<script>` blocks are not candidates for transformation.

Thin integrations consume that shared core:

- Vite: `build/vite-plugins/shopware-setup`
- Jest: `test/transformer/shopwareSetupVueTransformer.js`
- ESLint: `sw-core-rules/valid-shopware-setup`
- Volar / `vue-tsc`: `build/vue-setup-transform/volar-language-plugin.js`

Vue's SFC parser deliberately treats fake `<script setup sw-component>` text inside HTML comments, templates, styles, and script bodies as non-top-level content. Malformed sections fail loudly instead of producing partial transforms.

## Biome and oxlint outlook

Direct Biome support is not part of v1. Biome's Vue support is still partial, so Shopware setup lowering should stay behind the shared preprocessor boundary until Biome has a stable SFC extension point.

Direct oxlint support is not part of v1. oxlint has JavaScript plugin support for ESLint-compatible rules, but that path is still documented as alpha. Keeping `valid-shopware-setup` as a focused ESLint-compatible rule and keeping parser logic outside the rule preserves the cleanest migration path.

## Proposals

Full macro support could be added by mapping selected Vue macros to explicit Shopware equivalents. For example, `defineProps()` would need a clear integration point with normal SFC prop declarations and `useSwProps()`, without making props part of the public/private state return.

Top-level await could be supported only if the extension runtime becomes async-first for both base setup and override application. Until that runtime contract changes, v1 keeps top-level setup synchronous.

## Discussion

Open design questions should be recorded here:

- How should prop declarations be authored for pure Shopware setup SFC base components without reintroducing unsupported Vue macros?
- Should string-literal public keys that are not valid JavaScript identifiers receive a documented template aliasing convention?
- Which future Volar plugin API version should be treated as the minimum supported editor integration target?
