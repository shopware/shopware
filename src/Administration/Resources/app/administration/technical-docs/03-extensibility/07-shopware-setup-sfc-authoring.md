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

swDefineOverride({
    doubled,
});
</script>
```

The two modes are mutually exclusive. Mode attributes must be static quoted strings. Bound attributes such as `:sw-component="name"` and `v-bind:sw-override="name"` are rejected.

## Runtime Lowering

The preprocessor runs before Vue compiles the SFC. Base components are lowered through `createScriptSetupExtendableComponent()`, which is a thin bridge to `createExtendableSetup(...)`. Overrides are lowered to import-time `overrideComponentSetup(...)` registration so imported override SFC files register without needing to be mounted.

Base mode is auto-private by default. Supported top-level local runtime bindings become private state unless they are listed in `swDefinePublic({...})`. Public state is the public override API surface. Private state is still normal component/template state; it is only hidden from the top-level public override API and remains available to overrides through `_private`.

Override mode requires `swDefineOverride({...})`. Only bindings listed there replace base state. Override-local bindings are returned under deterministic private aliases only when they are referenced inside `<sw-block extends>` template content, and the transform merges those aliases into the block's default slot scope.

Runtime inputs are explicit composable-style accessors:

- Base: `useSwProps()`, `useSwContext()`
- Override: `useSwPreviousState()`, `useSwProps()`, `useSwContext()`

These are transform-injected local helpers, not broad runtime globals.

## Setup Macros

`swDefinePublic({...})` is the public marker in base mode.

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

`swDefineOverride({...})` is the override payload marker in override mode.

Supported:

```text
swDefineOverride({});
swDefineOverride({ count });
swDefineOverride({ count: localCount });
swDefineOverride({ 'count': localCount });
```

Rejected:

```ts
swDefineOverride({ [dynamicKey]: count });
swDefineOverride({ ...state });
swDefineOverride(state);
```

Override mode requires exactly one top-level `swDefineOverride({...})` call. Template-only overrides use `swDefineOverride({})`.

## Not Native Setup Semantics

Shopware setup blocks differ from native Vue `<script setup>` in v1:

- Author code is lowered into Shopware base/override callback contracts.
- Base public/private state is explicit Shopware extension state, not native setup return behavior.
- Override SFCs register with `overrideComponentSetup(...)` at import time.
- Base components may use one props declaration macro, either `defineProps(...)` or `withDefaults(defineProps(...), ...)`; the declaration is hoisted once and original calls are replaced with the props object passed into the extendable setup runtime.
- Other Vue macros are not supported in Shopware setup blocks.
- Top-level `await` is not supported.

## Unsupported in v1

The transform rejects these cases loudly:

- Script languages other than `js`, `jsx`, `ts`, and `tsx`
- Bound `sw-component` or `sw-override` attributes
- Vue macros except supported base props declarations: `defineEmits()`, `defineExpose()`, `defineOptions()`, `defineSlots()`, `defineModel()`
- Props declaration macros in override mode, or more than one props declaration macro
- Top-level `await`
- Non-top-level, duplicate, spread, computed-key, or non-object-literal `swDefinePublic()` usage
- Missing, non-top-level, duplicate, spread, computed-key, or non-object-literal `swDefineOverride()` usage in override mode
- Unsupported top-level declaration shapes such as destructuring declarations, except declarations that read from `defineProps()` or `withDefaults(defineProps(...), ...)`
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

Full macro support could be added by mapping more Vue macros to explicit Shopware equivalents. Props declaration macros are currently supported for base components by hoisting one props declaration and replacing the original call inside the extendable setup callback.

Top-level await could be supported only if the extension runtime becomes async-first for both base setup and override application. Until that runtime contract changes, v1 keeps top-level setup synchronous.

## Discussion

Open design questions should be recorded here:

- Should string-literal public keys that are not valid JavaScript identifiers receive a documented template aliasing convention?
- Which future Volar plugin API version should be treated as the minimum supported editor integration target?
