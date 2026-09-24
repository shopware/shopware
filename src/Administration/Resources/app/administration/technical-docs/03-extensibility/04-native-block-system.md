# The Native `sw-block` System in Shopware 6 Administration

> **Status**: Introduced in Shopware 6.8 — experimental, coexists with the TwigJS block system. The future target is to fully replace TwigJS blocks with this approach.
>
> **Companion document**: See [`05-twig-block-system.md`](./05-twig-block-system.md) for the current TwigJS block system.

---

## Why It Exists

The TwigJS block system requires the administration to run template compilation at **runtime**: raw `.html.twig` strings are parsed and merged by TwigJS, and the resulting HTML is handed to Vue. This works, but it has costs:

- Runtime performance overhead from template compilation
- Templates must be written in `.html.twig` files instead of SFCs
- No TypeScript awareness inside templates
- Harder to trace override issues because TwigJS merges templates as strings outside the normal Vue component/devtools flow

The **native block system** replaces all of that with pure Vue 3 components. Blocks and overrides are registered and resolved using Vue's reactivity model, so there is no separate compilation step and no secondary templating language.

---

## The Two Components

### `sw-block` — the extension point

`sw-block` serves double duty depending on which props it receives:

| Mode | Props | Purpose |
|------|-------|---------|
| **Define** | `name` | Creates an extension point with default content |
| **Override** | `extends` | Registers new content for a named extension point — **renders nothing itself** |

> **`<sw-block extends>` is registration-only.** When the `extends` prop is set, `sw-block` registers its default slot as a layer of the named block and renders nothing where the tag appears. The position of `<sw-block extends>` inside a template is therefore irrelevant to rendering. The only requirement is that the component is **mounted** (not blocked from mounting by an ancestor `v-if`), so that the layer is registered.

### `sw-block-parent` — the parent content placeholder

Used inside an override block to render the content from the previous block in the chain (the default content, or the previous override). Equivalent to `{% parent %}` in the TwigJS system.

---

## Basic Usage

### Defining an extension point

In a component template:

```html
<sw-block name="sw_product_detail_summary">
    <p>Default summary content</p>
</sw-block>
```

- `name` — unique identifier for this block, scoped globally across the app. Block names follow the same convention as TwigJS blocks: `sw_` prefix + snake_case (e.g., `sw_product_detail_summary`).
- The block's data scope is wired by the Shopware setup transform; `name` (or `extends`) is the only binding an author writes on `<sw-block>`.

### Complete end-to-end example

The following shows both sides together: the base component that declares the block and a plugin component that overrides it.

```html
<!-- ── Base component: sw-product-detail.vue ── -->
<div class="sw-product-detail">
    <sw-block name="sw_product_detail_summary">
        <p>Default summary content</p>
    </sw-block>
</div>
```

```html
<!-- ── Plugin override component template ── -->
<!--                                                                    -->
<!-- <sw-block extends> renders nothing at the position it is placed.   -->
<!-- Its slot is registered globally and picked up by the named block.  -->
<sw-block extends="sw_product_detail_summary">
    <sw-block-parent />
    <p class="my-badge">Added by MyPlugin</p>
</sw-block>
```

Rendered output:
```html
<div class="sw-product-detail">
    <p>Default summary content</p>   <!-- rendered by <sw-block-parent /> -->
    <p class="my-badge">Added by MyPlugin</p>
</div>
```

### Overriding a block (replace)

```html
<!-- Replaces the default content entirely -->
<sw-block extends="sw_product_detail_summary">
    <p class="custom-summary">My custom summary</p>
</sw-block>
```

### Extending a block (wrap / append)

```html
<!-- Keeps the default content and adds to it -->
<sw-block extends="sw_product_detail_summary">
    <sw-block-parent />
    <div class="custom-badge">New!</div>
</sw-block>
```

`<sw-block-parent />` renders whatever the previous block in the chain produced. Placing it before or after your content controls the insertion point:

```html
<!-- Prepend: custom content appears BEFORE default -->
<sw-block extends="sw_product_detail_summary">
    <div class="prepended">I go first</div>
    <sw-block-parent />
</sw-block>

<!-- Append: custom content appears AFTER default -->
<sw-block extends="sw_product_detail_summary">
    <sw-block-parent />
    <div class="appended">I go last</div>
</sw-block>
```

---

## Multiple Overrides Chaining

Multiple `sw-block extends="..."` blocks for the same name are supported and form a **chain**. Each override's `<sw-block-parent />` renders the previous override's output (not the original default directly).

```html
<!-- Override 1 -->
<sw-block extends="sw_product_detail_summary">
    <sw-block-parent />
    <div class="from-plugin-a">Added by Plugin A</div>
</sw-block>

<!-- Override 2 -->
<sw-block extends="sw_product_detail_summary">
    <sw-block-parent />
    <div class="from-plugin-b">Added by Plugin B</div>
</sw-block>
```

**Rendered output:**
```
[default content]
[Plugin A addition]
[Plugin B addition]
```

When there are multiple overrides and none uses `<sw-block-parent />`, only the **last registered** override is rendered. The earlier ones are silently discarded:

```html
<sw-block extends="sw_product_detail_summary">
    <div class="from-plugin-a">Plugin A (never shown)</div>
</sw-block>

<sw-block extends="sw_product_detail_summary">
    <div class="from-plugin-b">Plugin B (shown)</div>
</sw-block>
```

---

## Accessing State Around a Block

Override blocks are rendered outside the component they extend, so they have no implicit access to its reactive state. State flows through the Shopware setup transform instead:

- The owning component's data scope is wired to every named `<sw-block>` by the transform, which is how `<sw-block-parent />` content keeps rendering with the base component's state.
- Inside `<sw-block extends>` content, an override references its **own setup bindings** directly — the transform forwards every one of them to the block content. Public base state is read through `useSwPreviousState()`:

```vue
<template>
<sw-block extends="sw_product_price_display">
    <sw-block-parent />
    <span class="custom-price">{{ customPrice }}</span>
</sw-block>
</template>
<script setup>
import { computed } from 'vue';

const previousState = useSwPreviousState();
const customPrice = computed(() => `${previousState.price.value} €`);

swDefineOverride({});
</script>
```

See [`07-native-setup-authoring.md`](./07-native-setup-authoring.md) for the authoring rules.

---

## Nested Blocks

Blocks can be nested freely. Each block is independently overrideable:

```html
<!-- Component template -->
<sw-block name="sw_product_tabs">
    <div class="tabs">
        <sw-block name="sw_product_tab_basic">
            <span>Basic Info</span>
        </sw-block>

        <sw-block name="sw_product_tab_advanced">
            <span>Advanced</span>
        </sw-block>
    </div>
</sw-block>

<!-- Plugin: add a new tab without touching the outer block -->
<sw-block extends="sw_product_tabs">
    <sw-block-parent />
    <span>Custom Tab</span>
</sw-block>
```

New named blocks are declared by the base components that own them; override files use `extends` to contribute into existing blocks.

---

## How It Works Internally

### The block layer registry

`use-block-context.ts` keeps the layers of every block name. A layer is a render function
`(scope, frame) => VNode[]`. Two sources feed it:

- **Native layers**: every `<sw-block extends="...">` adds its default slot when it sets up and removes it when
  it unmounts. The registry is a `shallowReactive` `Map<blockName, Layer[]>` whose arrays are replaced, never
  mutated, so a registration re-renders only the blocks of that name. That is what makes an extension work when
  it mounts after the block rendered, e.g. when both sit in one template. Vue swaps an extension's slot function
  without a reactive signal when the scope around it changes, so an updated extension re-adds its layer.
- **Twig layers**: the `{% block %}`s of `Component.override()` / `Component.extend()` templates, indexed at boot
  by `twig-block-index.ts` (see [06](./06-twig-native-block-adapter.md)).

`getBlockLayers(name, host)` merges both and orders them with one rule:

1. inheritance depth: the position of the layer's component in the host's `extends` chain; a component's own
   `Component.extend()` template comes before the overrides of that component. Native layers and components that
   are not in the chain count as the base;
2. priority: the override index passed to `Component.override()`, `0` otherwise;
3. Twig layers before native layers;
4. registration order.

With default priorities this gives `default content → Twig overrides → native overrides`, and for Twig layers
it is the order in which TwigJS merges the same templates.

### `sw-block` render logic

A `<sw-block name>` renders every layer in order, bottom first, within one render:

```ts
const scope = props.data ?? getBlockDataScope(host);
const rendered = [slots.default?.(scope) ?? []];
getBlockLayers(props.name, host).forEach((layer) => rendered.push(layer.render(scope, frame)));

return rendered.length === 1 ? reduceToSingleRoot(rendered[0]) : h(SwBlockLayers, { layers: rendered });
```

- `host` is the component whose template declares the block (`vnode.ctx`), not the component that renders it
  into a slot. Without `:data` the block uses the host's data scope, so slot patterns never destructure `null`.
- A block without layers renders its default content directly: no extra component, no provide.
- Rendering all layers in one pass, in order, is what lets the legacy `v-if` bridge see earlier cases first.
- `SwBlockLayers` renders the top layer and receives the rendered layers as a prop. Every `<sw-block-parent>`
  below reads them from there, so it re-renders whenever the block renders new layers, without any reactive
  state written during render.

#### Why the single-root reduction matters

Calling a slot yields an array, and Vue turns any array — even one of length 1 — into a fragment. A
component rendering a fragment has no root element, so Vue has nowhere to put the attributes a
caller passes it, and `$el` is the fragment's text anchor rather than an element. Every directive
and caller that measures or appends to `$el` then breaks, `v-popover` and `v-tooltip` among them.

`reduceToSingleRoot()` therefore returns the one node when the block content really is
single-rooted, leaving a component built out of blocks as single-rooted as it was without them.
Content that genuinely has several roots is returned untouched — it was a fragment either way.

Comments are two-sided here: an author's `<!-- … -->` does not count as a root, because the
production compiler drops it and dev and prod have to agree on the root shape, but the placeholder
comment a falsy `v-if` renders does count. Dropping that one would make the component single-rooted
while the condition is falsy and multi-rooted once it flips, and Vue answers a changed root type
with an unmount plus remount.

### `sw-block-parent` render logic

`SwBlockLayers` provides `{ layers, index: top - 1 }`. A `<sw-block-parent>` injects the nearest context, renders
`layers[index]` and provides `index - 1` to its own subtree. The layer is therefore found by the position of the
`<sw-block-parent>` in the tree, not by setup order, so it works inside `v-if`, `v-for`, lazy tabs, modals and
async components. Outside of a layer it renders nothing.

### Data flow diagram

```
<sw-block name="foo">                       layers: default, Twig shim, native override
│
│  render: rendered = [default(scope), shim(scope), native(scope)]
│  → <SwBlockLayers :layers="rendered">     provides index 1, renders rendered[2]
│
│       ↓ inside the native override ↓
│  <sw-block-parent />                      injects index 1, renders rendered[1], provides index 0
│
│       ↓ inside the Twig shim ↓
│  <sw-block-parent />                      injects index 0, renders rendered[0] (default content)
```

---

## Lifecycle Reactivity

- An override's content appears as soon as its `<sw-block extends="...">` mounts, wherever it is placed.
- It disappears when it unmounts (e.g., when a plugin's component is conditionally hidden with `v-if`).
- Mounting an extension again does not register it twice.

---

## Comparison with the TwigJS Block System

| Aspect | TwigJS `{% block %}` | Native `<sw-block>` |
|--------|----------------------|---------------------|
| Template file | `.html.twig` | Vue SFC `<template>` |
| Resolution time | Runtime string merge | Vue render |
| Parent content | `{% parent %}` | `<sw-block-parent />` |
| Data access | Via `$super`, `this` in JS | Setup bindings (`useSwPreviousState()`, generated block scope) |
| TypeScript support | None inside templates | Full (slot typing, props) |
| Performance | Runtime TwigJS compilation | Standard Vue rendering |
| Debugging | Difficult (string merging) | Standard Vue devtools |
| Stability | Stable public API | Experimental (6.8+) |
| Works with SFCs | No | Yes |

---

## Known Limitations

From the ADR (`2024-09-26-native-block-system.md`):

**`v-if` / `v-else` disruption** — inserting an `sw-block` between `v-if` and `v-else` siblings breaks Vue's conditional rendering, because the block inserts a DOM node between them:

```html
<!-- ❌ This breaks v-else -->
<div v-if="condition">...</div>
<sw-block name="sw_between_conditions">...</sw-block>
<div v-else>...</div>
```

**Slot composition breakage** — placing an `sw-block` between a `<template #slot>` and its intended parent component disrupts Vue's slot composition.

**Override-local state needs a native-setup host** — an override's `<sw-block extends>` content can read the override's own setup bindings (the transform forwards them through the block's generated data scope). That forwarding only works when the component actually rendering the block is itself a native-setup (Composition API) component. If the block is rendered by an Options API component, the block data scope has no override-local (`__swOverride`) channel, so override-local bindings are not available there. Read shared base state through `useSwPreviousState()` instead, which does not depend on this channel.

**`<sw-block extends>` inside `v-for`** — prohibited. Each iteration independently registers a layer, registering a separate override entry per list item and causing the override content to be rendered multiple times:

```html
<!-- ❌ Registers one override per list item -->
<template v-for="item in items">
    <sw-block extends="sw_product_detail_summary">
        <div>{{ item.name }}</div>
    </sw-block>
</template>
```

**Two unconditional top-level `<sw-block>`s make the component multi-root** — a `{% block %}` emitted no node of its own, but a `<sw-block>` is a component vnode. Two of them side by side leave the component without a root element, so callers lose every attribute they pass that is not a declared prop, and `$el` becomes a text anchor. Keep one top-level block and put the rest inside it:

```html
<!-- ❌ Two roots: the caller's class never lands, and $el is not an element -->
<sw-block name="sw_thing_new"><mt-thing v-if="useMeteor" /></sw-block>
<sw-block name="sw_thing_old"><sw-thing-deprecated v-else /></sw-block>

<!-- ✅ One root -->
<sw-block name="sw_thing">
    <mt-thing v-if="useMeteor" />
    <sw-thing-deprecated v-else />
</sw-block>
```

**A binding named after a component tag replaces that component** — a `<script setup>` template resolves a tag by trying it as written, camelized and capitalized, and prefers a setup binding of any of those names over the registered component. A `routerLink` prop next to a `<router-link>` in the same template therefore renders the prop's value instead of the link. Template refs are the common case, because a ref is usually named after what it points at:

```html
<!-- ❌ `swSelectResultList` is a setup binding, so the tag renders its value — `null` until mount -->
<sw-select-result-list ref="swSelectResultList" />

<!-- ✅ The ref no longer shares a name with the tag -->
<sw-select-result-list ref="resultList" />
```

Rename one side; `vue/no-dupe-keys` does not cover this case, and nothing fails at build time.

> **Note:** `<sw-block extends>` inside `v-if` is explicitly **supported**: the layer is registered on mount and removed on unmount. See [Lifecycle Reactivity](#lifecycle-reactivity) above.

---

## Summary

The `sw-block` system replaces TwigJS block inheritance with two Vue components:

- `<sw-block name="...">` — declares an extension point with default content; reactively incorporates any registered overrides at render time
- `<sw-block extends="...">` — registers override content for a named block; renders nothing itself, just adds its slot to the global registry
- `<sw-block-parent />` — renders the previous content in the chain (default or prior override), found by its position in the tree

The block layer registry (`useBlockContext`) maps block names to layers, native and Twig alike, ordered by one rule. The last layer is the outermost one; `<sw-block-parent />` walks backwards through the chain via Vue's `inject`.
