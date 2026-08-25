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

> **`<sw-block extends>` is registration-only.** When the `extends` prop is set, `sw-block` registers its default slot in the global block registry and returns `{ template: null }` — no HTML is emitted at the location where the tag appears. The position of `<sw-block extends>` inside a template is therefore irrelevant to rendering. The only requirement is that the component is **mounted** (not blocked from mounting by an ancestor `v-if`) so that `addBlock()` is called and the slot is picked up by the target `<sw-block name>`.

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
- Inside `<sw-block extends>` content, an override references its **own setup bindings** directly — the transform detects the references and exposes them to the block content. Public base state is read through `useSwPreviousState()`:

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

### The global block registry

The block system uses a module-level reactive object as its registry, exposed via the `useBlockContext` composable:

```1:46:src/Administration/Resources/app/administration/src/app/composables/use-block-context.ts
const blockContext: Record<string, Slot[]> = reactive({});

function getBlocks(blockName: string): Slot[] {
    return blockContext[blockName] ?? [];
}

function addBlock(blockName: string, block?: Slot): void {
    if (!block) {
        return;
    }
    if (!blockContext[blockName]) {
        blockContext[blockName] = [];
    }
    blockContext[blockName].push(block);
}

function removeBlock(blockName: string, block?: Slot): void {
    if (!block) {
        return;
    }
    if (!blockContext[blockName]) {
        return;
    }
    blockContext[blockName] = blockContext[blockName].filter((b) => b !== block);

    if (blockContext[blockName].length === 0) {
        delete blockContext[blockName];
    }
}
```

The registry maps a block name to an ordered array of Vue `Slot` functions. Every `sw-block extends="..."` adds its default slot to this array on mount and removes it on `onBeforeUnmount`.

### `sw-block` render logic

```148:172:src/Administration/Resources/app/administration/src/app/component/structure/sw-block-override/sw-block/index.ts
        const template = computed(() => {
            if (!props.name) {
                throw new Error('[sw-block] The "name" prop is required when "extends" is not set.');
            }

            // shimSlots come before nativeBlocks so that Twig plugin overrides (registered
            // at boot time) are positioned below native <sw-block extends> overrides
            // (registered at mount time), matching the expected stacking order:
            //   default → shim (legacy plugin) → native (newer plugin or core extension)
            const nativeBlocks = getBlocks(props.name);
            const blocksAndParent = [
                slots.default ?? (() => []),
                ...shimSlots,
                ...nativeBlocks,
            ];
            const blocksNodes = blocksAndParent.map((block) => block?.(props.data));

            const lastNode = blocksNodes.pop();
            // Each <sw-block-parent /> calls .pop() exactly once in its own setup()
            // to claim its parent slot. The array must be reset to the current render's
            // ordered list so that each parent instance pops the correct slot — not a
            // stale or accumulated list from a previous render cycle.
            providedParents.value = blocksNodes;
            return lastNode;
        });
```

`shimSlots` is built once in `setup()` from the legacy TwigJS overrides for this block name (via `getBlockEntries`), giving each shim a stable VNode type so reactive updates don't remount it.

The key steps when rendering a **named block** (`name` prop):

1. Build the ordered slot array `[defaultSlot, ...shimSlots, ...nativeBlocks]` — the default content, then legacy Twig-plugin overrides (shim slots), then native `<sw-block extends>` overrides from `getBlocks(name)`
2. Call each slot function with the `data` prop (making scope available)
3. **Pop the last element** — that is what actually gets rendered
4. **Assign all others** to the `providedParents` ref (exposed via `provide`), replacing the previous list so stale entries are released
5. **Reduce the winning slot's nodes to a single root** where it has one, so the block renders that node rather than a fragment around it

This is why the last registered override wins when no `<sw-block-parent />` is used.

#### Why the reduction in step 5 matters

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

```16:42:src/Administration/Resources/app/administration/src/app/component/structure/sw-block-override/sw-block-parent/index.ts
export default Shopware.Component.wrapComponentConfig({
    setup() {
        const parents = inject(parentsInjectionKey, null);
        const initialParents = parents?.value;
        const initialParent = initialParents?.pop();
        const parentIndex = initialParents ? initialParents.length : -1;
        // Reserve the stack slot once, then read the current VNode at that slot after reactive parent updates.
        const parent = computed(() => {
            if (parentIndex < 0 || !parents || parents.value === initialParents) {
                return initialParent;
            }

            return parents.value[parentIndex];
        });

        return {
            parent,
        };
    },
    // The parent content is returned directly instead of through a wrapping functional component:
    // a fresh arrow function as the VNode type on every render reads to Vue as a different
    // component and makes it unmount plus remount the content, and a functional component would
    // additionally swallow every fallthrough attribute except class, style and listeners.
    render() {
        return reduceToSingleRoot(this.parent);
    },
});
```

`sw-block-parent` **injects** the `providedParents` array from the nearest ancestor `sw-block` (via Vue's provide/inject using a Symbol key) and **pops** its last element **once** during `setup()`, claiming the previous block in the chain. It remembers that slot index and reads it through a `computed`, so when `sw-block` re-renders and replaces `providedParents.value`, the parent re-reads the current VNode at its reserved slot instead of popping again. It renders that as its output, through the same single-root reduction `sw-block` uses.

### Data flow diagram

```
Component with <sw-block name="foo"> (data scope wired by the setup transform)
│
│  Mount
│
│  Compose [defaultSlot, ...shimSlots, ...getBlocks("foo")]
│  → [defaultSlot, shimSlot, nativeSlot]   (one legacy shim, one native override)
│
│  Call each slot with $dataScope
│  → [defaultVNodes, shimVNodes, nativeVNodes]
│
│  providedParents.value = [defaultVNodes, shimVNodes]
│  render → nativeVNodes   ← last one wins
│
│       ↓ inside nativeVNodes template ↓
│
│  <sw-block-parent />
│  setup() reserves slot 1, computed reads providedParents.value[1]
│  → shimVNodes   ← previous in chain
│
│       ↓ inside shimVNodes template ↓
│
│  <sw-block-parent />
│  setup() reserves slot 0, computed reads providedParents.value[0]
│  → defaultVNodes
```

---

## Lifecycle Reactivity

Because override `sw-block` components register and deregister themselves using Vue's lifecycle hooks, the system is fully reactive to mounting and unmounting:

- An override's content appears as soon as the `sw-block extends="..."` mounts
- It disappears when it unmounts (e.g., when a plugin's component is conditionally hidden with `v-if`)
- Multiple mount/unmount cycles do not accumulate duplicates

This is verified in the test suite — toggling `v-if` on an override component correctly adds and removes its contribution without leaving stale entries.

---

## Comparison with the TwigJS Block System

| Aspect | TwigJS `{% block %}` | Native `<sw-block>` |
|--------|----------------------|---------------------|
| Template file | `.html.twig` | Vue SFC `<template>` |
| Resolution time | Build-time string merge | Vue reactive runtime |
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

**`<sw-block-parent />` inside `v-for`** — prohibited. Each list iteration creates a separate `sw-block-parent` instance, and each calls `.pop()` on the shared `providedParents` array during `setup()`. Multiple pops in a single render pass consume more parent slots than intended, silently corrupting the chain:

```html
<!-- ❌ Multiple instances each pop() a different slot from the chain -->
<sw-block extends="sw_product_detail_summary">
    <template v-for="item in items">
        <sw-block-parent />
    </template>
</sw-block>
```

**`<sw-block-parent />` inside `v-if`** — unsupported. A toggle that unmounts then remounts `<sw-block-parent />` re-runs `setup()`, which calls `.pop()` again. The parent `sw-block` resets `providedParents` in its `template` computed on each re-render, but the interleaving between that reset and the child's mount order is not guaranteed to be safe:

```html
<!-- ❌ Re-mounting sw-block-parent calls .pop() again -->
<sw-block extends="sw_product_detail_summary">
    <sw-block-parent v-if="condition" />
    <div>My content</div>
</sw-block>
```

**`v-if` / `v-else` directly on `<sw-block-parent />`** — prohibited. `<sw-block-parent />` must not be part of a Vue conditional chain. It must always render unconditionally inside an extending block, otherwise local `v-else` branches can break the parent chain resolution:

```html
<!-- ❌ Conditional sw-block-parent breaks the parent chain -->
<sw-block extends="sw_product_detail_summary">
    <sw-block-parent v-if="showParent" />
    <div v-else>Local fallback</div>
</sw-block>
```

**Override-local state needs a native-setup host** — an override's `<sw-block extends>` content can read the override's own setup bindings (the transform forwards them through the block's generated data scope). That forwarding only works when the component actually rendering the block is itself a native-setup (Composition API) component. If the block is rendered by an Options API component, the block data scope has no override-local (`__swOverride`) channel, so override-local bindings are not available there. Read shared base state through `useSwPreviousState()` instead, which does not depend on this channel.

**`<sw-block extends>` inside `v-for`** — prohibited. Each iteration independently calls `addBlock()`, registering a separate override entry per list item and causing the override content to be rendered multiple times:

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

> **Note:** `<sw-block extends>` inside `v-if` is explicitly **supported**. The `addBlock`/`removeBlock` lifecycle hooks handle registration and deregistration correctly as the component mounts and unmounts. See [Lifecycle Reactivity](#lifecycle-reactivity) above.

---

## Summary

The `sw-block` system replaces TwigJS block inheritance with two Vue components:

- `<sw-block name="...">` — declares an extension point with default content; reactively incorporates any registered overrides at render time
- `<sw-block extends="...">` — registers override content for a named block; renders nothing itself, just adds its slot to the global registry
- `<sw-block-parent />` — renders the previous content in the chain (default or prior override), via Vue's provide/inject

The global block registry (`useBlockContext`) is a reactive module-level map of block names to ordered slot arrays. The last registered override is always the outermost render layer; `<sw-block-parent />` walks backwards through the chain via Vue's `inject`.
