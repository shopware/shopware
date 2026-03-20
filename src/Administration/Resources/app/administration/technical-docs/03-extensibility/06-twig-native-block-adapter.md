# Twig → Native Block Runtime Adapter

**Issue**: [shopware/shopware#14970](https://github.com/shopware/shopware/issues/14970)

---

## Overview

As Shopware migrates Administration components from the TwigJS block system (`{% block %}` / `{% parent %}`) to native Vue blocks (`<sw-block>` / `<sw-block-parent />`), plugin developers who have existing Twig-based template overrides would immediately lose compatibility. This adapter bridges the two systems at runtime so that legacy Twig overrides continue to work on migrated components without any changes from plugin developers.

The adapter activates automatically — zero action required from core developers when migrating a component and zero action from plugin developers. It emits a deprecation warning per affected block to guide plugin developers toward native syntax.

---

## Problem

Plugin developers override component templates using:

```js
Shopware.Component.override('sw-product-detail', {
    template: `
{% block sw_product_detail_content %}
    {% parent %}
    <div class="my-extension" v-if="product.active">
        {{ product.name }}
    </div>
{% endblock %}
`,
});
```

When `sw-product-detail` migrates its template from `{% block sw_product_detail_content %}` to `<sw-block name="sw_product_detail_content">`, TwigJS can no longer find the block to merge the override into. The plugin's content is silently dropped.

---

## Design Principles

**1. Zero touch for core developers**
When migrating a component template, a core developer only replaces `{% block foo %}...{% endblock %}` with `<sw-block name="foo" :data="$dataScope">...</sw-block>`. Nothing else. The adapter detects the legacy override automatically.

**2. Zero touch for plugin developers**
Existing `Shopware.Component.override()` calls with Twig block templates continue to work. A `console.warn` tells the developer what to migrate and to which native syntax.

**3. Factory-independent**
The adapter hooks into `<sw-block>` itself, not the component factory. This means it works whether the parent component is registered through `Shopware.Component.register()` or is a pure Vue SFC — the `<sw-block>` tag is always present in the template and always mounts.

**4. Fast block lookup at render time (no Twig parsing on the hot path)**
The block index is built when overrides are registered (typically during boot). At render time, resolving entries for a given `name` is a single `Map` lookup by block name — **O(1) in the number of indexed block names**. Other work still scales with how many Twig overrides exist for that name (each becomes one shim slot) and with native `<sw-block extends="…">` registrations handled separately. We avoid stating a single Big-O for “all of `sw-block` setup”: e.g. every `extends` instance participates in the block context, and conditional mounting (`v-if`) can defer work until the subtree exists.

**5. No TwigJS rendering**
TwigJS is used only as an AST parser to extract the block structure. The inner content is reconstructed verbatim from the token tree and compiled by Vue's own runtime template compiler — giving full Vue reactivity, including `v-if`, `v-for`, `{{ }}` interpolation, and event handlers.

---

## Why Vue Directives Work

Shopware's `template.factory.js` globally strips TwigJS's output token definitions at startup:

```js
TwigCore.token.definitions = TwigCore.token.definitions.filter((token) => {
    return (
        token.type !== TwigCore.token.type.output_whitespace_pre &&
        token.type !== TwigCore.token.type.output_whitespace_post &&
        token.type !== TwigCore.token.type.output_whitespace_both &&
        token.type !== TwigCore.token.type.output  // ← {{ }} disabled
    );
});
```

Consequently, from TwigJS's perspective:

- `{{ product.name }}` — not a recognized token; stored as a raw text node, passed through verbatim
- `v-if`, `@click`, `:title` — HTML attribute strings; raw text, passed through verbatim
- `{% block %}` / `{% parent %}` — the **only** logic tokens TwigJS processes

The inner content of any `{% block %}` is therefore already valid Vue template HTML. The adapter reconstructs it from the token tree and passes it as a `template` property on the ShimContent component options — Vue's runtime template compiler then compiles it on first mount and caches the result internally.

---

## Architecture

```
Boot time
─────────────────────────────────────────────────────────────────────
Shopware.Component.override('sw-product-detail', { template: '...' })
    │
    ├─ existing ──► TemplateFactory.registerTemplateOverride()
    │
    └─ NEW ───────►     indexTwigBlocksFromTemplate(componentName, rawTemplate)
                        │
                        │  parse TwigJS token tree
                        │  extract each {% block name %}
                        │  reconstruct inner Vue template string
                        │  replace {% parent %} → <sw-block-parent />
                        ▼
                    blockIndex: Map<blockName, BlockEntry[]>
                    {
                      'sw_product_detail_content': [{
                          componentName: 'sw-product-detail',
                          innerTemplate: '...<div v-if="product.active">...',
                      }]
                    }

There is no separate “base vs extended” branch inside `indexTwigBlocksFromTemplate`: it only records **legacy Twig** `{% block name %}…{% endblock %}` bodies from `Shopware.Component.override()` templates. **Base vs extension is decided later at runtime by which `sw-block` props are used:**

- **`name`** — the component defines the extension point (“base” default content). If the Twig index has entries for that name, `sw-block` builds **shim** slot functions from those entries and composes them with the default slot and any native overrides (see below).
- **`extends`** — the component **consumes** a block by that name: it registers its default slot with `addBlock` / `removeBlock` on the shared block context (same mechanism as a native `<sw-block extends="…">`). The adapter does not index `extends` templates; it only bridges Twig overrides **into** the `name` side.

Runtime (first mount of a given block name)
─────────────────────────────────────────────────────────────────────
<sw-block name="sw_product_detail_content" :data="$dataScope"> mounts
    │
    ├─ hasBlockEntries('sw_product_detail_content') → true (Twig index lookup)
    │
    ├─ shimSlots = getBlockEntries(...).map(entry => createShimSlot(entry, name))
    │       builds ShimContent with { template: innerTemplate }
    │       Vue compiles the template on first mount and caches internally
    │       returns: Slot = (dataScope) => [h(ShimContent)]
    │
    └─ computed template merges [ defaultSlot, ...shimSlots, ...nativeExtendsSlots ]
           (shim slots are **not** registered via addBlock; native `<sw-block extends>`
           still uses addBlock/removeBlock on the block context)
           │
           └─ sw-block renders the composed chain
                  <sw-block-parent /> resolves from sw-block's provide() stack ✓
                  {{ product.name }} reactive via ShimContent setup() context ✓
```

---

## Implementation

### 1. Block Index — `src/core/factory/twig-block-index.ts`

Built at override registration time. Resolving entries by block name is a `Map` lookup — **O(1) in the number of distinct indexed names**; iterating shim entries for a name is linear in how many Twig overrides target that name.

```ts
import Twig from 'twig';
import reconstructInnerTemplate from './reconstruct-twig-template';

export interface BlockEntry {
    componentName: string;
    innerTemplate: string;
}

const blockIndex = new Map<string, BlockEntry[]>();

export function indexTwigBlocksFromTemplate(componentName: string, rawTemplate: string): void {
    let parsed: ReturnType<typeof Twig.twig>;
    try {
        parsed = Twig.twig({ data: rawTemplate, rethrow: true });
    } catch {
        return;
    }

    parsed.tokens
        .filter((token) => token.type === 'logic' && !!token.token?.blockName)
        .forEach((token) => {
            const blockName = token.token!.blockName as string;
            const output = token.token!.output ?? [];
            const innerTemplate = reconstructInnerTemplate(output);

            const entries = getBlockEntries(blockName);
            entries.push({ componentName, innerTemplate });
            blockIndex.set(blockName, entries);
        });
}

export function getBlockEntries(blockName: string): BlockEntry[] {
    return blockIndex.get(blockName) ?? [];
}

export function hasBlockEntries(blockName: string): boolean {
    return blockIndex.has(blockName);
}
```

### 2. Template Reconstruction — `src/core/factory/reconstruct-twig-template.ts`

Walks the TwigJS token tree and reconstructs the raw Vue-compatible template string without invoking TwigJS's renderer. The `{% parent %}` custom tag is registered with `type: 'parent'` via `Twig.extendTag` in `template.factory.js`, and block tokens are identified by their `blockName` property.

```ts
export default function reconstructInnerTemplate(tokens: TwigToken[]): string {
    return tokens
        .map((token) => {
            if (token.type === 'raw') {
                return token.value ?? '';
            }

            if (token.type === 'logic') {
                if (token.token?.type === 'parent') {
                    return '<sw-block-parent />';
                }

                if (token.token?.blockName !== undefined) {
                    return reconstructInnerTemplate(token.token.output ?? []);
                }
            }

            return '';
        })
        .join('');
}
```

### 3. Slot Factory — `src/app/component/structure/sw-block-override/shim/create-shim-slot.ts`

Builds a ShimContent component definition using the reconstructed template string and returns a slot function compatible with `sw-block`'s `blockContext`. Vue's runtime template compiler handles the `template` string on first mount and caches the result internally — no manual component definition caching is needed.

```ts
import { h, type Slot } from 'vue';
import type { BlockEntry } from 'src/core/factory/twig-block-index';
import swBlockParent from '../sw-block-parent/index';

const warnedBlocks = new Set<string>();

export function createShimSlot(entry: BlockEntry, blockName: string): Slot {
    if (!warnedBlocks.has(blockName)) {
        warnedBlocks.add(blockName);
        console.warn(
            `[Shopware Deprecation] Block "${blockName}" in component "${entry.componentName}" ` +
                `uses a legacy Twig override. ` +
                `Migrate to: <sw-block extends="${blockName}">...</sw-block>`,
        );
    }

    const def = {
        name: `__twig-shim__${blockName}`,
        template: entry.innerTemplate,
        components: { 'sw-block-parent': swBlockParent },
    };

    return (dataScope) => [h({ ...def, setup: () => buildSetupContext(dataScope) })];
}
```

**`dataScope`** is the value passed through `<sw-block :data="…">` (in templates often `:data="$dataScope"`): the **host component instance proxy** whose fields the legacy Twig override expects (`product`, `this`, etc.). The slot function receives that same object so ShimContent can run in the parent's reactive scope.

**`buildSetupContext`** uses a Proxy (not `Object.keys` enumeration) to give ShimContent's compiled render function transparent, reactive read access to every public property of that host proxy — without triggering Vue's `ownKeys` warning. `Object.keys()` on a Vue component proxy returns an empty array in production mode and logs a warning in development, making plain enumeration broken. The Proxy delegates `get` to the component proxy so Vue's reactivity system tracks each read as a dependency.

**How `<sw-block-parent />` works:** `ShimContent` is rendered inside `sw-block`'s render tree. `sw-block` already `provide()`s the parent VNode stack via `parentsInjectionKey`. `<sw-block-parent />` injects from that stack and pops the previous content — exactly as a natively written `<sw-block extends="...">` would behave. The `components: { 'sw-block-parent': swBlockParent }` registration ensures the component is available even in test environments where only local components are registered.

### 4. Hook into `async-component.factory.ts`

Two indexing paths are added to the `override()` function to handle both synchronous (direct object) and asynchronous (lazy-loaded function) config shapes:

```ts
// Synchronous indexing for direct-object configs (the common case)
let alreadyIndexed = false;
if (typeof componentConfiguration !== 'function') {
    const { template: tpl } = componentConfiguration;
    if (typeof tpl === 'string') {
        indexTwigBlocksFromTemplate(componentName, tpl);
        alreadyIndexed = true;
    }
}

const configResolveMethod = async (): Promise<ComponentConfig> => {
    // ... resolve config ...

    if (config.template) {
        // Async path: index here for lazy-loaded plugin overrides
        if (!alreadyIndexed) {
            indexTwigBlocksFromTemplate(componentName, config.template as string);
        }

        TemplateFactory.registerTemplateOverride(componentName, config.template as string, overrideIndex);
        delete config.template;
    }
    // ...
};
```

### 5. Hook into `sw-block-override/sw-block/index.ts`

The **`name`** branch in `setup()` checks the **Twig block index** (legacy `{% block %}` overrides) and builds **shim** slot functions once. **`hasBlockEntries` / `getBlockEntries`** read that index. **`addBlock` / `removeBlock`** from `useBlockContext()` are only used on the **`extends`** branch (native overrides); Twig shims are merged in the `computed` template together with the default slot and `getBlocks(name)` — they are **not** registered through `addBlock`, so multiple `<sw-block name="foo">` instances do not share one global shim registration.

```ts
const shimSlots: Slot[] =
    props.name && hasBlockEntries(props.name)
        ? getBlockEntries(props.name).map(entry => createShimSlot(entry, props.name))
        : [];

const template = computed(() => {
    if (!props.name) {
        return null;
    }

    const nativeBlocks = getBlocks(props.name);
    const blocksAndParent = [
        slots.default ?? (() => []),
        ...shimSlots,
        ...nativeBlocks,
    ];
    // ... providedParents + lastNode (see component source)
});
```

When the defining `sw-block` unmounts, `shimSlots` go out of scope with the instance; native `extends` registrations still use `onBeforeUnmount` + `removeBlock` in the `extends` path of the same file.

---

## File Overview

| File | Type | Purpose |
|------|------|---------|
| `core/factory/twig-block-index.ts` | New | Block name index (Map), built at registration time |
| `core/factory/reconstruct-twig-template.ts` | New | TwigJS token tree → Vue template string |
| `shim/create-shim-slot.ts` | New | Slot function factory, Proxy-based setup context |
| `core/factory/async-component.factory.ts` | Modified | +16 lines: sync + async `indexTwigBlocksFromTemplate` calls |
| `sw-block-override/sw-block/index.ts` | Modified | Shim slots + compose order in `setup()` / `computed` |

---

## Known Limitations

### ⚠ `{% if %}` / `{% for %}` inside block content are silently dropped

Twig control-flow tags inside a `{% block %}` body are **not** supported. `reconstructInnerTemplate`
collapses any token it does not recognise — anything that is not a raw HTML fragment, a
`{% parent %}` call, or a nested `{% block %}` — to an empty string. There is **no error or
warning**; the content simply does not render.

**Before (broken after component migration):**

```js
Shopware.Component.override('sw-product-detail', {
    template: `
{% block sw_product_detail_content %}
    {% if product.active %}
        <div class="active-badge">Active</div>
    {% endif %}
{% endblock %}
`,
});
```

The `{% if %}` tag is silently dropped; the block renders as empty.

**Migrate to Vue directives instead:**

```js
Shopware.Component.override('sw-product-detail', {
    template: `
<sw-block extends="sw_product_detail_content">
    <div v-if="product.active" class="active-badge">Active</div>
</sw-block>
`,
});
```

Vue `v-if`, `v-for`, and `{{ }}` interpolation work fully inside native `<sw-block>` overrides
because the runtime template compiler handles them — this limitation only affects the legacy
Twig shim path.

---

### Vue component references inside Twig overrides (e.g. `<sw-card>`)

Resolved by Vue's runtime compiler using the global component registry — works as expected.

---

### Async overrides and boot-order invariant

**1. Synchronous config (object)** — `Shopware.Component.override('sw-foo', { template: '...' })` runs `indexTwigBlocksFromTemplate` immediately in `override()` when the second argument is a plain object with a string `template`.

**2. Async function config** — `Shopware.Component.override('sw-foo', async () => ({ template: '...' }))` (or any factory that returns a `Promise` of the config). Indexing runs inside `configResolveMethod` when that promise resolves, **before** the merged template is registered — see `async-component.factory.ts`. This is how lazy-loaded plugins ship overrides.

**Invariant:** Twig blocks must be indexed **before** the first time a `<sw-block name="…">` for that name runs, so `hasBlockEntries` / `getBlockEntries` see data. Today, Shopware’s boot awaits component config resolution before mounting the admin Vue app, so the async path still completes in time.

**When the invariant is violated:** The async override resolves **after** the block has already mounted (e.g. if something mounted Vue earlier, or the boot order changed), the Twig blocks were never indexed for that template, `hasBlockEntries` stays `false`, and **only the default `name` content** appears — the legacy override appears skipped.

**Why no warning:** The failure mode is indistinguishable from “no plugin registered a Twig override for this block” without tracking “expected but missing” indexing. Adding noise would require new instrumentation (e.g. dev-only checks that a pending async override still matched). Today it fails quietly like any absent override.

This boot-order dependency is enforced by convention, not by a runtime guard in the adapter. If the application boot sequence is ever restructured, this must be re-validated.

---

## Migration Guide for Plugin Developers

When Shopware emits a deprecation warning for your block override, migrate from:

```js
// Before — Twig block syntax
Shopware.Component.override('sw-product-detail', {
    template: `
{% block sw_product_detail_content %}
    {% parent %}
    <div class="my-extension" v-if="product.active">
        {{ product.name }}
    </div>
{% endblock %}
`,
});
```

To:

```js
// After — native sw-block syntax
Shopware.Component.override('sw-product-detail', {
    template: `
<sw-block extends="sw_product_detail_content">
    <sw-block-parent />
    <div class="my-extension" v-if="product.active">
        {{ product.name }}
    </div>
</sw-block>
`,
});
```

Mapping:

| Twig | Native |
|------|--------|
| `{% block name %}...{% endblock %}` | `<sw-block extends="name">...</sw-block>` |
| `{% parent %}` | `<sw-block-parent />` |

---

## Implementation Effort

| Task | Estimate |
|---|---|
| `twig-block-index.ts` + `reconstruct-twig-template.ts` | 1.5 days |
| `create-shim-slot.ts` | 1–2 days |
| `async-component.factory.ts` hook | 0.5 days |
| `sw-block-override/sw-block/index.ts` bridge | 0.5 days |
| Deprecation warning deduplication | 0.5 days |
| Unit + integration tests | 2–3 days |
| **Total** | **~6–8 days** |
