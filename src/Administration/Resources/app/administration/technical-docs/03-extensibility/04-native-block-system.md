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

In a component template (SFC or `.html.twig`):

```html
<sw-block name="sw_product_detail_summary" :data="$dataScope">
    <p>Default summary content</p>
</sw-block>
```

- `name` — unique identifier for this block, scoped globally across the app. Block names follow the same convention as TwigJS blocks: `sw_` prefix + snake_case (e.g., `sw_product_detail_summary`).
- `:data="$dataScope"` — passes the component's entire data/computed/methods scope to any override that wants it (more on this below)

### Complete end-to-end example

The following shows both sides together: the base component that declares the block and a plugin component that overrides it.

```html
<!-- ── Base component: sw-product-detail.html.twig ── -->
<div class="sw-product-detail">
    <sw-block name="sw_product_detail_summary" :data="$dataScope">
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

## Accessing the Component's Data Scope

Override blocks are rendered outside the component they extend, so they normally have no access to its reactive data. The `data` prop and the slot's default scope solve this.

### Passing data

The component that owns the block passes itself down via `:data="$dataScope"`:

```html
<!-- In the component being extended -->
<sw-block name="sw_product_price_display" :data="$dataScope">
    <span>{{ product.price }}</span>
</sw-block>
```

`$dataScope` is a helper that returns the current component's proxy (`getCurrentInstance()?.proxy`), which exposes all `data`, `computed`, and `methods`.

### Consuming data in an override

The override block receives the scope as its default slot argument:

```html
<sw-block extends="sw_product_price_display" #default="{ product, formatPrice }">
    <sw-block-parent />
    <span class="custom-price">{{ formatPrice(product.price) }}</span>
</sw-block>
```

This is standard Vue scoped slot syntax — `#default="{ ... }"` destructures whatever the `data` prop provided.

---

## Nested Blocks

Blocks can be nested freely. Each block is independently overrideable:

```html
<!-- Component template -->
<sw-block name="sw_product_tabs" :data="$dataScope">
    <div class="tabs">
        <sw-block name="sw_product_tab_basic" :data="$dataScope">
            <span>Basic Info</span>
        </sw-block>

        <sw-block name="sw_product_tab_advanced" :data="$dataScope">
            <span>Advanced</span>
        </sw-block>
    </div>
</sw-block>

<!-- Plugin: add a new tab without touching the outer block -->
<sw-block extends="sw_product_tabs">
    <sw-block-parent />
    <sw-block name="sw_product_tab_custom" :data="$dataScope">
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

```59:114:src/Administration/Resources/app/administration/src/app/component/structure/sw-block-override/sw-block/index.ts
export default Shopware.Component.wrapComponentConfig({
    props: {
        name: {
            type: String,
        },
        extends: {
            type: String,
        },
        data: {
            type: Object as PropType<ComponentInternalInstance['proxy']>,
            default: null,
        },
    },
    setup(props, { slots }) {
        const { addBlock, removeBlock, getBlocks } = useBlockContext();
        if (props.extends) {
            addBlock(props.extends, slots.default);

            onBeforeUnmount(() => {
                if (props.extends) {
                    removeBlock(props.extends, slots.default);
                }
            });

            return { template: null };
        }

        const providedParents = ref<ReturnType<Slot>[]>([]);
        provide(parentsInjectionKey, providedParents);

        const template = computed(() => {
            if (!props.name) {
                throw new Error('[sw-block] The "name" prop is required when "extends" is not set.');
            }

            const blocks = getBlocks(props.name);
            const blocksAndParent = [
                slots.default ?? (() => []),
                ...blocks,
            ];
            const blocksNodes = blocksAndParent.map((block) => block?.(props.data));

            const lastNode = blocksNodes.pop();
            // Reset the list on every render so unconsumed entries from the previous cycle
            // are released and each sw-block-parent pops the correct slot.
            providedParents.value = blocksNodes;
            return lastNode;
        });

        return {
            template,
        };
    },
    render() {
        return this.template;
    },
});
```

The key steps when rendering a **named block** (`name` prop):

1. Retrieve all registered override slots from `getBlocks(name)`
2. Build an array: `[defaultSlot, ...overrideSlots]`
3. Call each slot function with the `data` prop (making scope available)
4. **Pop the last element** — that is what actually gets rendered
5. **Assign all others** to the `providedParents` ref (exposed via `provide`), replacing the previous list so stale entries are released

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
│  provide(parentsInjectionKey, [defaultVNodes, override1VNodes])
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

**`<sw-block extends>` inside `v-for`** — prohibited. Each iteration independently calls `addBlock()`, registering a separate override entry per list item and causing the override content to be rendered multiple times:

```html
<!-- ❌ Registers one override per list item -->
<template v-for="item in items">
    <sw-block extends="sw_product_detail_summary">
        <div>{{ item.name }}</div>
    </sw-block>
</template>
```

> **Note:** `<sw-block extends>` inside `v-if` is explicitly **supported**. The `addBlock`/`removeBlock` lifecycle hooks handle registration and deregistration correctly as the component mounts and unmounts. See [Lifecycle Reactivity](#lifecycle-reactivity) above.

---

## Summary

The `sw-block` system replaces TwigJS block inheritance with two Vue components:

- `<sw-block name="...">` — declares an extension point with default content; reactively incorporates any registered overrides at render time
- `<sw-block extends="...">` — registers override content for a named block; renders nothing itself, just adds its slot to the global registry
- `<sw-block-parent />` — renders the previous content in the chain (default or prior override), via Vue's provide/inject

The global block registry (`useBlockContext`) is a reactive module-level map of block names to ordered slot arrays. The last registered override is always the outermost render layer; `<sw-block-parent />` walks backwards through the chain via Vue's `inject`.
