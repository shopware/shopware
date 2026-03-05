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

**4. O(1) at render time**
The block index is fully built at override registration time (during boot), before any Vue component mounts. At render time, `<sw-block>` does a single Map lookup.

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

The inner content of any `{% block %}` is therefore already valid Vue template HTML. The adapter reconstructs it from the token tree and compiles it with `compileToFunction` — exactly how Shopware's existing TwigJS pipeline hands templates to Vue.

---

## Architecture

```
Boot time
─────────────────────────────────────────────────────────────────────
Shopware.Component.override('sw-product-detail', { template: '...' })
    │
    ├─ existing ──► TemplateFactory.registerTemplateOverride()
    │
    └─ NEW ───────► indexTwigBlocksFromTemplate(componentName, rawTemplate)
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
                          hasParent: true,
                      }]
                    }

Runtime (first mount of a given block name)
─────────────────────────────────────────────────────────────────────
<sw-block name="sw_product_detail_content" :data="$dataScope"> mounts
    │
    ├─ hasBlockEntries('sw_product_detail_content') → true
    │
    ├─ createShimSlot(entry)
    │       compileToFunction(innerTemplate)  ← Vue runtime compiler, once per template
    │       returns: Slot = (dataScope) => [h(ShimContent)]
    │
    └─ addBlock('sw_product_detail_content', shimSlot)
           │
           └─ sw-block renders the slot natively
                  <sw-block-parent /> resolves from sw-block's provide() stack ✓
                  {{ product.name }} reactive via ShimContent setup() context ✓
```

---

## Implementation

### 1. Block Index — `src/app/component/structure/sw-block-override/shim/block-index.ts`

Built at override registration time. Provides O(1) lookup for `sw-block` at mount time.

```ts
import Twig from 'twig';
import { reconstructInnerTemplate, containsParentToken } from './reconstruct-template';

export interface BlockEntry {
    componentName: string;
    innerTemplate: string;
    hasParent: boolean;
}

const blockIndex = new Map<string, BlockEntry[]>();

export function indexTwigBlocksFromTemplate(
    componentName: string,
    rawTemplate: string,
): void {
    let parsed: ReturnType<typeof Twig.twig>;
    try {
        parsed = Twig.twig({ data: rawTemplate, rethrow: true });
    } catch {
        return; // malformed template — TwigJS will also fail later; skip silently
    }

    for (const token of parsed.tokens) {
        if (token.type !== 'logic') continue;
        if (token.token?.type !== 'Twig.logic.type.block') continue;

        const blockName = token.token.name as string;
        const innerTemplate = reconstructInnerTemplate(token.token.output);
        const hasParent = containsParentToken(token.token.output);

        const existing = blockIndex.get(blockName) ?? [];
        existing.push({ componentName, innerTemplate, hasParent });
        blockIndex.set(blockName, existing);
    }
}

export function getBlockEntries(blockName: string): BlockEntry[] {
    return blockIndex.get(blockName) ?? [];
}

export function hasBlockEntries(blockName: string): boolean {
    return blockIndex.has(blockName);
}
```

### 2. Template Reconstruction — `src/app/component/structure/sw-block-override/shim/reconstruct-template.ts`

Walks the TwigJS token tree and reconstructs the raw Vue-compatible template string without invoking TwigJS's renderer.

```ts
export function reconstructInnerTemplate(tokens: TwigToken[]): string {
    return tokens.map((token) => {
        if (token.type === 'raw') {
            // HTML, Vue directives, {{ interpolation }} — verbatim passthrough
            return token.value as string;
        }

        if (token.type === 'logic') {
            if (token.token?.type === 'Twig.logic.type.parent') {
                // {% parent %} → native <sw-block-parent />
                return '<sw-block-parent />';
            }

            if (token.token?.type === 'Twig.logic.type.block') {
                // Nested {% block %} — recurse into its content
                return reconstructInnerTemplate(token.token.output);
            }
        }

        return '';
    }).join('');
}

export function containsParentToken(tokens: TwigToken[]): boolean {
    return tokens.some(
        (t) => t.type === 'logic' && t.token?.type === 'Twig.logic.type.parent',
    );
}
```

### 3. Slot Factory — `src/app/component/structure/sw-block-override/shim/create-shim-slot.ts`

Compiles the reconstructed template with Vue's runtime compiler and returns a slot function compatible with `sw-block`'s `blockContext`.

```ts
import { compileToFunction } from '@vue/runtime-dom';
import { h, type Slot } from 'vue';
import type { BlockEntry } from './block-index';

// Compiled render functions are cached by inner template string
const renderFnCache = new Map<string, ReturnType<typeof compileToFunction>>();

// Deprecation warnings are emitted once per block name
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

    let renderFn = renderFnCache.get(entry.innerTemplate);
    if (!renderFn) {
        renderFn = compileToFunction(entry.innerTemplate);
        renderFnCache.set(entry.innerTemplate, renderFn);
    }

    const compiledRenderFn = renderFn;

    return (dataScope) => {
        const ShimContent = {
            name: `__twig-shim__${blockName}`,
            render: compiledRenderFn,
            setup: () => buildSetupContext(dataScope),
        };

        return [h(ShimContent)];
    };
}

function buildSetupContext(dataScope: object | null): Record<string, unknown> {
    if (!dataScope) return {};

    const ctx: Record<string, unknown> = {};
    for (const key of Object.keys(dataScope)) {
        if (key.startsWith('$') || key.startsWith('_')) continue;
        ctx[key] = (dataScope as Record<string, unknown>)[key];
    }
    return ctx;
}
```

**How reactivity works:** `sw-block`'s `template` computed re-evaluates whenever its `data` prop changes (which holds `$dataScope` — the component proxy). Each re-evaluation re-calls the slot function with a fresh proxy, which rebuilds `buildSetupContext`. Vue then re-renders `ShimContent` with the updated context. All `{{ }}` interpolations, `v-if` conditions, and event handlers inside the override template update correctly.

**How `<sw-block-parent />` works:** `ShimContent` is rendered inside `sw-block`'s render tree. `sw-block` already `provide()`s the parent VNode stack via `parentsInjectionKey`. `<sw-block-parent />` injects from that stack and pops the previous content — exactly as a natively written `<sw-block extends="...">` would behave.

### 4. Hook into `async-component.factory.ts`

One line added to the `override()` function, inside `configResolveMethod`, before the template string is deleted:

```ts
if (config.template) {
    indexTwigBlocksFromTemplate(componentName, config.template as string); // ← NEW

    TemplateFactory.registerTemplateOverride(componentName, config.template as string, overrideIndex);
    delete config.template;
}
```

### 5. Hook into `sw-block/index.ts`

The `name`-prop path in `setup()` gains an early check against the block index:

```ts
if (props.name) {
    // Bridge legacy Twig overrides targeting this block name
    if (hasBlockEntries(props.name)) {
        const entries = getBlockEntries(props.name);
        const shimSlots = entries.map(entry => createShimSlot(entry, props.name!));

        shimSlots.forEach(slot => addBlock(props.name!, slot));
        onBeforeUnmount(() => {
            shimSlots.forEach(slot => removeBlock(props.name!, slot));
        });
    }

    // ... existing sw-block render logic unchanged
}
```

Lifecycle-driven cleanup: when the host component unmounts (e.g., navigating away), `onBeforeUnmount` removes the shim slots from `blockContext`. On remount, they are re-added. This matches the behavior of native `<sw-block extends="...">` exactly.

---

## File Overview

| File | Type | Purpose |
|------|------|---------|
| `shim/block-index.ts` | New | Block name index, built at registration time |
| `shim/reconstruct-template.ts` | New | TwigJS token tree → Vue template string |
| `shim/create-shim-slot.ts` | New | Slot function factory, Vue compiler integration |
| `async-component.factory.ts` | Modified | +1 line: call `indexTwigBlocksFromTemplate` |
| `sw-block/index.ts` | Modified | +~10 lines: shim bridge in `setup()` |

---

## Known Limitations

| Limitation | Notes |
|---|---|
| `{% if %}` / `{% for %}` inside block content are unsupported | These Twig control flow tags were never valid in Shopware's admin block system. They produce empty strings in `reconstructInnerTemplate`. Must be documented in the migration guide. |
| Vue component references inside Twig overrides (e.g. `<sw-card>`) | Resolved by Vue's runtime compiler using the global component registry — works as expected. |
| Async overrides indexed after first `sw-block` mount | In Shopware's boot sequence, `initComponent()` awaits all override configs before Vue mounts. In practice this cannot be hit. |
| `compileToFunction` requires Vue's runtime compiler | Already present in Shopware admin — the entire existing template pipeline relies on it. |

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
| `block-index.ts` | 0.5 days |
| `reconstruct-template.ts` | 1 day |
| `create-shim-slot.ts` | 1–2 days |
| `async-component.factory.ts` hook | 0.5 days |
| `sw-block/index.ts` bridge | 0.5 days |
| Deprecation warning deduplication | 0.5 days |
| Unit + integration tests | 2–3 days |
| Documentation & migration guide | 0.5 days |
| **Total** | **~6–9 days** |
