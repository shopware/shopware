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
- Complex debugging when override chains go wrong

The **native block system** replaces all of that with pure Vue 3 components. Blocks and overrides are registered and resolved using Vue's reactivity model, so there is no separate compilation step and no secondary templating language.

That also addresses the debugging problem: extension points are ordinary Vue components (`<sw-block>` / `<sw-block extends="…">` / `<sw-block-parent />`), so block names and the override relationship show up as props in the component tree and in Vue DevTools instead of having to reason about merged `.html.twig` strings. (The comparison table below calls this out in the **Debugging** row.)

---

## The Two Components

### `sw-block` — the extension point

`sw-block` serves double duty depending on which props it receives:

| Mode | Props | Purpose |
|------|-------|---------|
| **Define** | `name` | Creates an extension point with default content |
| **Override** | `extends` | Registers new content for a named extension point |

### `sw-block-parent` — the parent content placeholder

Used inside an override block to render the content from the previous block in the chain (the default content, or the previous override). Equivalent to `{% parent %}` in the TwigJS system.

---

## Basic Usage

### Defining an extension point

In a component template (SFC or `.html.twig`):

```html
<sw-block name="product-detail-summary" :data="$dataScope">
    <p>Default summary content</p>
</sw-block>
```

- `name` — unique identifier for this block, scoped globally across the app
- `:data="$dataScope"` — passes the component's entire data/computed/methods scope to any override that wants it (more on this below)

**Naming convention:** TwigJS blocks were typically named with a `sw_` prefix and snake_case (for example `sw_product_detail_content`). The native system does not add or strip prefixes—the `name` string **is** the registry key and must match `extends` exactly. Templates migrated from `{% block … %}` keep the same identifier (the codemod copies the block name into `name="…"`). The examples above use **kebab-case** to mirror common Vue attribute style for new extension points; either kebab-case or the legacy `sw_snake_case` shape is fine as long as it stays stable and unique app-wide.

### Overriding a block (replace)

```html
<!-- Replaces the default content entirely -->
<sw-block extends="product-detail-summary">
    <p class="custom-summary">My custom summary</p>
</sw-block>
```

### Registration-only: what `<sw-block extends>` actually does

`<sw-block extends="…">` **does not emit any HTML at the location of the tag**. It only **registers** the default slot with the global block registry on mount (and removes it on unmount). The implementation returns no render output for `extends` mode, so there is no “wrapper” node you can style or see in the DOM at the override site.

The overridden content is composed **only when the extension point renders**—that is, where the base component declares `<sw-block name="product-detail-summary">` (same string as `extends`). You therefore **cannot** reposition the visible result by moving `<sw-block extends>` up or down in your plugin template; layout follows the **named** block in the core (or merged) template.

**Does placement matter?** For *where* the final markup appears in the UI: **no**—not in the sense of template order in your override file. For *stacking order when several plugins override the same block name*, **mount / registration order** still matters (see [Multiple Overrides Chaining](#multiple-overrides-chaining)); that is lifecycle-driven, not “higher vs. lower in the file as layout.”

### Extending a block (wrap / append)

```html
<!-- Keeps the default content and adds to it -->
<sw-block extends="product-detail-summary">
    <sw-block-parent />
    <div class="custom-badge">New!</div>
</sw-block>
```

`<sw-block-parent />` renders whatever the previous block in the chain produced. Placing it before or after your content controls the insertion point:

```html
<!-- Prepend: custom content appears BEFORE default -->
<sw-block extends="product-detail-summary">
    <div class="prepended">I go first</div>
    <sw-block-parent />
</sw-block>

<!-- Append: custom content appears AFTER default -->
<sw-block extends="product-detail-summary">
    <sw-block-parent />
    <div class="appended">I go last</div>
</sw-block>
```

---

## End-to-end example: plugin `Shopware.Component.override` + `<sw-block extends>`

Typical extension: register an override on an existing admin component and supply a `.html.twig` file that only adds native block overrides (you do not need to copy the full base template).

**`src/Extension/sw-product-detail/index.js`** (same pattern works in `.ts`):

```javascript
import template from './sw-product-detail.html.twig';

Shopware.Component.override('sw-product-detail', {
    template,
});
```

Load that file from your plugin’s `main.js` (or equivalent) so the override runs during boot, as with any other `Shopware.Component.override`.

**`src/Extension/sw-product-detail/sw-product-detail.html.twig`** — only the blocks you need. Each `<sw-block extends="…">` registers an override for the matching `name` in the merged `sw-product-detail` template. The tags can appear anywhere in this file; they still **do not** render at those positions—output appears only at each corresponding `<sw-block name="…">` in the merged component template.

```html
<sw-block extends="sw_product_detail_reviews">
    <sw-block-parent />
    <sw-card title="Plugin: extra reviews panel">
        <p>Additional markup from a plugin.</p>
    </sw-card>
</sw-block>
```

Together: **`Shopware.Component.override`** contributes another layer to the Twig-merge pipeline for that component; **`TemplateFactory`** produces the merged template string; when Vue renders it, **`<sw-block extends>`** only registers slots, and the visible result is composed at the core **`<sw-block name="sw_product_detail_reviews">`** (or the migrated equivalent) when that part of the tree renders.

---

## Multiple Overrides Chaining

Multiple `sw-block extends="..."` blocks for the same name are supported and form a **chain**. Each override's `<sw-block-parent />` renders the previous override's output (not the original default directly).

```html
<!-- Override 1 -->
<sw-block extends="product-detail-summary">
    <sw-block-parent />
    <div class="from-plugin-a">Added by Plugin A</div>
</sw-block>

<!-- Override 2 -->
<sw-block extends="product-detail-summary">
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
<sw-block extends="product-detail-summary">
    <div class="from-plugin-a">Plugin A (never shown)</div>
</sw-block>

<sw-block extends="product-detail-summary">
    <div class="from-plugin-b">Plugin B (shown)</div>
</sw-block>
```

---

## Accessing the Component's Data Scope

Override blocks are rendered outside the component they extend, so they normally have no access to its reactive data. The `data` prop and the slot's default scope solve this.

### Passing data

The component that owns the block passes itself down via `:data="$dataScope"`:

```html
<!-- In the component being extended -->
<sw-block name="product-price-display" :data="$dataScope">
    <span>{{ product.price }}</span>
</sw-block>
```

`$dataScope` is a helper that returns the current component's proxy (`getCurrentInstance()?.proxy`), which exposes all `data`, `computed`, and `methods`.

### Consuming data in an override

The override block receives the scope as its default slot argument:

```html
<sw-block extends="product-price-display" #default="{ product, formatPrice }">
    <sw-block-parent />
    <span class="custom-price">{{ formatPrice(product.price) }}</span>
</sw-block>
```

This uses Vue's scoped-slot syntax (`#default="{ ... }"` destructures the object the `data` prop supplies). That is deliberate: `sw-block` repurposes the scoped-slot channel to forward reactive state into override templates (which render at the extension point), rather than for the usual parent→child slot composition where the child's job is to render the parent's slot content. The API matches Vue; the primary role here is data plumbing across the block boundary.

---

## Nested Blocks

Blocks can be nested freely. Each block is independently overrideable:

```html
<!-- Component template -->
<sw-block name="product-tabs" :data="$dataScope">
    <div class="tabs">
        <sw-block name="product-tab-basic" :data="$dataScope">
            <span>Basic Info</span>
        </sw-block>

        <sw-block name="product-tab-advanced" :data="$dataScope">
            <span>Advanced</span>
        </sw-block>
    </div>
</sw-block>

<!-- Plugin: add a new tab without touching the outer block -->
<sw-block extends="product-tabs">
    <sw-block-parent />
    <sw-block name="product-tab-custom" :data="$dataScope">
        <span>Custom Tab</span>
    </sw-block>
</sw-block>
```

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

The component has two paths: `extends` registers an override slot (`template: null` at the tag), and `name` defines the extension point. The **named block** path also prepends [Twig shim slots](06-twig-native-block-adapter.md) when present, then native `getBlocks(name)` overrides. The following excerpt is the core render stack for `name`:

```118:145:src/Administration/Resources/app/administration/src/app/component/structure/sw-block-override/sw-block/index.ts
        const providedParents = ref<ReturnType<Slot>[]>([]);
        provide(parentsInjectionKey, providedParents);

        const template = computed(() => {
            if (!props.name) {
                return null;
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

The key steps when rendering a **named block** (`name` prop):

1. Build the ordered slot list: default slot, then Twig shim slots (if any), then slots from `getBlocks(name)`
2. Map each slot to VNodes by calling it with the `data` prop (making scope available)
3. **Pop the last element** — that is what actually gets rendered (outermost layer in the chain)
4. **Assign the remaining nodes** to `providedParents` (a fresh array every time the template `computed` runs — not accumulated across renders). That reset avoids holding a stale parent stack when Vue re-renders (e.g. after `v-if` toggles) and limits retention of unused parent VNode lists to the current evaluation.

This is why the last registered override wins when no `<sw-block-parent />` is used.

### `sw-block-parent` render logic

```1:26:src/Administration/Resources/app/administration/src/app/component/structure/sw-block-override/sw-block-parent/index.ts
import { h, inject } from 'vue';
import parentsInjectionKey from '../sw-block/parents-injection-key';

export default Shopware.Component.wrapComponentConfig({
    setup() {
        const parent = inject(parentsInjectionKey, null)?.value.pop();

        return {
            parent,
        };
    },
    render() {
        return h(() => this.parent);
    },
});
```

`sw-block-parent` **injects** the `providedParents` array from the nearest ancestor `sw-block` (via Vue's provide/inject using a Symbol key), and **pops** the last element from it — which is the pre-rendered VNode array of the previous block in the chain. It then renders that as its output.

If there is **no** matching `provide` (for example, `<sw-block-parent />` is used outside any `<sw-block name="…">` subtree), `inject` returns the default `null`, the optional chain short-circuits, and the component renders nothing — **there is no runtime error or warning today.** Stricter behavior (e.g. a development-only error) would be a separate hardening change.

### Data flow diagram

```
Component with <sw-block name="foo" :data="$dataScope">
│
│  Mount
│
│  useBlockContext.getBlocks("foo")
│  → [defaultSlot, overrideSlot1, overrideSlot2]
│
│  Call each slot with $dataScope
│  → [defaultVNodes, override1VNodes, override2VNodes]
│
│  providedParents ← [defaultVNodes, override1VNodes]   ← replaced each render
│  render → override2VNodes   ← last one wins
│
│       ↓ inside override2VNodes template ↓
│
│  <sw-block-parent />
│  inject(parentsInjectionKey).pop()
│  → override1VNodes   ← previous in chain
│
│       ↓ inside override1VNodes template ↓
│
│  <sw-block-parent />
│  inject(parentsInjectionKey).pop()
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
| Template file | `.html.twig` | Any template (SFC, `.html.twig`) |
| Resolution time | Build-time string merge | Vue reactive runtime |
| Parent content | `{% parent %}` | `<sw-block-parent />` |
| Data access | Via `$super`, `this` in JS | Scoped slot `#default="{ ... }"` |
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
<sw-block name="between-conditions">...</sw-block>
<div v-else>...</div>
```

**Slot composition breakage** — placing an `sw-block` between a `<template #slot>` and its intended parent component disrupts Vue's slot composition.

**`sw-block-parent` / `sw-block extends` and `v-if` / `v-for`** — There is **no** documented blanket rule that forbids these inside conditionals or lists. The parent `<sw-block name="…">` **reassigns** `providedParents` on each template recomputation (see excerpt above), so the stack is rebuilt for that render; each `<sw-block-parent />` pops **once** when its component instance is created. Rerenders therefore re-run that pairing — they do not “stack” extra `.pop()` calls on an old array. What *does* break easily is **structural** misuse: if the number or order of `sw-block-parent` instances no longer matches the remaining chain (for example, a parent is behind `v-if` but a child override is not), pops no longer line up with the intended layers. Prefer a stable chain layout; use conditionals **inside** override content when you need branching, rather than gating individual `sw-block-parent` steps in ways that desynchronize the chain.

---

## Summary

The `sw-block` system replaces TwigJS block inheritance with two Vue components:

- `<sw-block name="...">` — declares an extension point with default content; reactively incorporates any registered overrides at render time
- `<sw-block extends="...">` — registers override content for a named block; renders nothing itself, just adds its slot to the global registry
- `<sw-block-parent />` — renders the previous content in the chain (default or prior override), via Vue's provide/inject

The global block registry (`useBlockContext`) is a reactive module-level map of block names to ordered slot arrays. The last registered override is always the outermost render layer; `<sw-block-parent />` walks backwards through the chain via Vue's `inject`.
