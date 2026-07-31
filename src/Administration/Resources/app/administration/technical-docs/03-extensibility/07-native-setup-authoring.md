# Native Setup Authoring

Native setup is the Administration term for Vue's native `<script setup>` SFC syntax. Shopware setup authoring is native setup plus a filename-based base/override convention for the Composition-API extension system. It is *not* plain native setup: the transform lowers the author body into Shopware's base/override callback contracts before Vue compiles the SFC.

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

const previousState = useSwPreviousState();
const doubled = computed(() => previousState.count.value * 2);

swDefineOverride({ doubled });
</script>
```

## Runtime lowering

The transform runs before Vue compiles the SFC.

**Base.** The author body stays exactly as written — plain `<script setup>`, macros in place, nothing hoisted or wrapped. The transform only (1) renames each top-level runtime binding to a reserved `__swSetupAuthor_<name>` alias and (2) appends a generated `Shopware.Component.attachOverrides({ public, private })` footer that re-declares the original names from the override wrapper. Templates read overrideable state exactly as before.

Base mode is **auto-private**: every supported top-level runtime binding becomes private state unless it is listed in `swDefinePublic({...})`, which every base component must declare (see [Setup markers](#setup-markers)). Private state is still normal component/template state — it is only hidden from the top-level public override API. Overrides reach it through the `_private` group of the previous-state payload (`override(({ publicName, _private }) => ...)`). Macro-derived bindings are treated the same way: `const props = defineProps(...)`, `const emit = defineEmits(...)`, and `const slots = defineSlots(...)` become private state under their declared names, so templates can reference `emit`, `slots`, and `props.<name>` directly.

The base transform also adds `:data="$dataScope"` to every `<sw-block name="...">`, forwarding the generated data scope to block overrides without every base author writing it by hand. The `data` binding and the default slot scope of `<sw-block>` are owned by the transform: authoring `data`, `#default`, or a `v-bind` object on `<sw-block>` is rejected.

**Override.** Overrides stay `<script setup>` components whose body registers an `overrideComponentSetup(...)` callback. Each override component is rendered once in a hidden container at boot; that mount runs the registration and lets `<sw-block extends>` template content register its block overrides. A template-less override receives a generated comment-only template so the hidden component can mount without a missing-render warning. Override mode requires exactly one top-level `swDefineOverride({...})`; only the bindings listed there replace base state.

Override-local bindings are returned under deterministic private aliases only when they are referenced inside `<sw-block extends>` content, forwarded through the reserved `__swOverride` slot-scope channel. Those forwarded template bindings are **read-only**: they arrive through the generated slot scope, so a template write (`@click="count = count + 1"`, `count++`) would assign to a slot-scope local and take no effect — a silent trap, since the same line works in a base component. The transform rejects such assignment and update expressions at build time. Mutate override state from a handler defined in the override setup and call that handler instead.

**Runtime inputs** are explicit:

- Base: `defineProps(...)`, `withDefaults(defineProps(...), ...)`, plus Vue's own composables (`useAttrs()`, `useSlots()`, …) — the body is native `<script setup>`.
- Override: `useSwPreviousState()`, `useSwProps()`, `useSwContext()` — transform-injected local helpers, override mode only. Base components get no injected helpers; their body runs natively.

## Props

Base components declare props with Vue's native `defineProps(...)` or `withDefaults(defineProps(...), ...)`. The macro stays where you wrote it and Vue compiles it — the transform never moves or rewrites it, so prop defaults, reactive destructuring, and `withDefaults` behave exactly as in any Vue 3.5 component. Read props through the props object (`props.count`) or a reactive destructure so access stays reactive, and keep defaults on the prop (a destructure default or `withDefaults`) rather than on a separate local, because the template reads the prop and not the local.

**One Shopware-specific rule:** a top-level setup binding must not share a declared prop's name.

```ts
const props = defineProps<{ count: number }>();
const count = ref(0);   // collides with the declared prop `count`
```

Native Vue lets the setup binding shadow the prop in the template; the extendable setup runtime does the opposite — it strips declared prop keys from returned state, so the binding would be deleted and `{{ count }}` would read `undefined`. Rename the local and read the prop through `props.count`.

This is caught by the [`vue/no-dupe-keys` ESLint rule](#editor-integration), not the build-time transform — so it surfaces in your editor and in `composer eslint:admin`. That covers an inline object literal and a type **declared in the same file**, which the transform itself cannot resolve:

```ts
interface Props { count: number }
const props = defineProps<Props>();   // vue/no-dupe-keys flags the `count` collision below
const count = ref(0);
```

**It does not cover an imported prop type.** Nothing resolves across files here, so this collision is reported by no rule and no build step — the template silently renders `undefined`:

```ts
import type { Props } from './props.types';   // export interface Props { count: number }

const props = defineProps<Props>();
const count = ref(0);                         // not reported anywhere
```

Sharing a props type between components is ordinary, so treat this as a case to watch for by hand: when props come from an imported type, check the names against your top-level bindings yourself.

## Setup markers

`swDefinePublic({...})` (base) marks the public override API; `swDefineOverride({...})` (override) marks the override payload. Both accept **only shorthand bindings**, so a key always equals its local binding name:

```text
swDefinePublic({ count });
swDefineOverride({});
swDefineOverride({ count });
```

Renaming, string keys, computed keys, spreads, and non-object-literal arguments are rejected: the transform, lint, and type layers need a stable compile-time key, and a renamed key could silently shadow another binding.

**Both markers are mandatory in their mode** — pass an empty object when there is nothing to declare (`swDefinePublic({})` for a base component with no public state, `swDefineOverride({})` for a template-only override). A transformed base component is an extension point: its filename becomes the public override target and its bindings become overrideable state. Requiring the marker keeps that from happening merely because a file carries a `<script setup>` block, and it tells a reader at a glance that the file is lowered rather than being a plain Vue SFC.

## Differences from native setup

- Base public/private state is explicit Shopware extension state, not native setup-return behaviour.
- Override SFCs register with `overrideComponentSetup(...)` at import time.
- **Base mode does not touch the Vue macros at all.** `defineProps`, `withDefaults`, `defineEmits`, `defineSlots`, `defineExpose`, and `defineOptions` stay where you wrote them and are compiled by Vue with their normal semantics — including Vue's own rules on how many times each may appear, and Vue's own diagnostics for macro arguments it cannot hoist. The transform only renames top-level bindings and appends the footer.
- **Override mode moves the author body into a callback**, so imports and type-only declarations (`interface`, `type`, ambient `declare`) are lifted back to the generated script root — matching how Vue keeps them at the module root. Ambient `declare` statements describe values provided elsewhere and are never returned as setup state.
- **Forwarded override bindings are read-only in the template.** Inside `<sw-block extends>` content a forwarded binding arrives ref-unwrapped as a slot-scope local, so Vue's compiler applies none of the ref handling it gives a setup binding — no `.value` write-through. A template write (`@click="count = count + 1"`, `count++`) reassigns the slot-scope local and silently no-ops, where the identical line mutates state in a base component. The transform rejects such writes at build time; mutate from a method defined in the override setup instead.
- Vue macros other than the base-mode set above are unsupported in either mode.
- Top-level `await` is unsupported.

## Rejected loudly

The transform rejects these at build time:

- Script languages other than `js`, `jsx`, `ts`, and `tsx`
- `defineModel()`, in either mode
- Base-mode macros used in override mode (`defineProps`, `withDefaults`, `defineEmits`, `defineExpose`, `defineSlots`, `defineOptions`)
- Override-only helpers (`useSwPreviousState()`, `useSwProps()`, `useSwContext()`) in base mode
- Top-level `await`
- An SFC without a `<script setup>` block — a plain `<script>` (Options API) or a template-only `.vue` file. Every `.vue` component is extendable, and the markers that declare that only exist in `<script setup>`, so such a file would compile into a component nothing can override
- Non-top-level, duplicate, spread, renamed/string/computed-key, or non-object-literal `swDefinePublic()` / `swDefineOverride()` usage
- A missing marker: no `swDefinePublic()` in a base component, or no `swDefineOverride()` in an override
- Authored `#default`, `data`, or `v-bind` bindings on `<sw-block>`
- Template writes to a forwarded override binding inside `<sw-block extends>` content
- Reserved top-level binding names:
  - the `__swSetup` prefix, used for the transform's generated bindings
  - `__swOverride`, the reserved override-private slot-scope token
  - `Shopware`, because the generated footer reads the global `Shopware` object
  - `__proto__`, because the generated state map emits `name: alias` properties where `__proto__:` is prototype-setter syntax, so the binding would be silently dropped
- Additional `<script>` blocks next to the Shopware setup block

Malformed or unclosed SFC sections are left to Vue's compiler parser: if `@vue/compiler-sfc` reports SFC parse errors, the preprocessor skips transformation so Vue can present the primary parse error first.

## Parser behaviour

All parser-sensitive behaviour lives in `build/vue-setup-transform`, with smaller helpers under `build/vue-setup-transform/utils`. SFC block detection uses `@vue/compiler-sfc` and reads `descriptor.scriptSetup`; mode and component name come from the normalized filename. A missing `<script setup>` block is an error rather than an opt-out, so the only `.vue` file the transform hands back untouched is one Vue's own parser already rejected. Vue's parser deliberately treats fake `<script setup>` text inside comments, templates, styles, and script bodies as non-top-level content.

## Editor integration

Every transform rejection above surfaces in your editor, not only at build time. The `valid-shopware-setup` ESLint rule (`eslint-rules/core-rules`) runs the *same* shared transform against the file and reports its errors on the offending line — so a reserved binding name, a renamed marker key, or a wrong-mode macro is flagged as you type. There is one validator; the build enforces it and this rule mirrors it into the editor.

The prop/binding name collision is the one detection that is **not** a transform rejection: it is caught by the standard `vue/no-dupe-keys` ESLint rule instead, which resolves prop names across every form — including a named type (`defineProps<Props>()`) the transform cannot see through. So it too is flagged in the editor and in `composer eslint:admin`, just via a different rule.
