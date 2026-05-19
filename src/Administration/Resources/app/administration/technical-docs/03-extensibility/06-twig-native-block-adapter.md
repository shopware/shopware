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

**4. Minimal overhead at render time**
The block index is fully built at override registration time (during boot), before any Vue component mounts. At render time, `<sw-block>` resolves entries from the prebuilt index instead of reparsing Twig templates. The setup phase (which can also run post-boot, e.g. when a `v-if` condition turns true) iterates over the registered extend instances for that block name — in practice a very small set, bounded by the number of active plugin overrides for a single block.

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
Boot time  (override() only — register() does not touch the block index)
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
                      }]
                    }

Runtime (first mount of a given block name)
─────────────────────────────────────────────────────────────────────
<sw-block name="sw_product_detail_content" :data="$dataScope"> mounts
    │
    ├─ hasBlockEntries('sw_product_detail_content') → true
    │
    ├─ createShimSlot(entry)
    │       builds ShimContent with { template: innerTemplate }
    │       Vue compiles the template on first mount and caches internally
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

### 1. Block Index — `src/core/factory/twig-block-index.ts`

Built at override registration time. At mount time, `sw-block` resolves entries from the prebuilt index instead of reparsing Twig templates.

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

            const existing = getBlockEntries(blockName);
            existing.push({ componentName, innerTemplate });
            blockIndex.set(blockName, existing);
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

**`dataScope`** is the Vue component proxy of the host component — the public instance exposing its data, computed properties, methods, and injections (equivalent to `this` in Options API or `getCurrentInstance().proxy` in Composition API). **`buildSetupContext`** receives this proxy and uses a `Proxy` (not `Object.keys` enumeration) to give ShimContent's compiled render function transparent, reactive read access to every public property — without triggering Vue's `ownKeys` warning. `Object.keys()` on a Vue component proxy returns an empty array in production mode and logs a warning in development, making plain enumeration broken. The Proxy delegates `get` to the component proxy so Vue's reactivity system tracks each read as a dependency.

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

### 5. Hook into `sw-block/index.ts`

Two separate function namespaces are involved here:

- `hasBlockEntries` / `getBlockEntries` — from `twig-block-index.ts`; they query the Twig block index built during boot.
- `addBlock` / `removeBlock` — from `useBlockContext()`; they register/deregister slots in the sw-block slot context (used by the `extends` path).

For the `name`-prop path, shim slots are **not** registered via `addBlock`. They are created once in `setup()` and stored in a local variable, keeping each `<sw-block name="...">` instance isolated:

```ts
const shimSlots: Slot[] =
    props.name && hasBlockEntries(props.name)
        ? getBlockEntries(props.name).map((entry) => createShimSlot(entry, props.name!))
        : [];
```

Shim slots are not registered in the global `blockContext` so that multiple simultaneous instances of `<sw-block name="foo">` each maintain their own isolated shim slots and cannot double-render each other's content.

---

## File Overview

| File | Type | Purpose |
|------|------|---------|
| `core/factory/twig-block-index.ts` | New | Block name index (Map), built at registration time |
| `core/factory/reconstruct-twig-template.ts` | New | TwigJS token tree → Vue template string |
| `shim/create-shim-slot.ts` | New | Slot function factory, Proxy-based setup context |
| `core/factory/async-component.factory.ts` | Modified | +16 lines: sync + async `indexTwigBlocksFromTemplate` calls |
| `sw-block/index.ts` | Modified | +25 lines: shim bridge in `setup()` |

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

An **async function config** is when you pass a function to `Shopware.Component.override()`
instead of a plain object:

```js
// Direct-object config (synchronous) — block index populated immediately at registration time
Shopware.Component.override('sw-product-detail', {
    template: `{% block sw_product_detail_content %}...{% endblock %}`,
});

// Async function config (lazy-loaded) — block index populated later when configResolveMethod is awaited
Shopware.Component.override('sw-product-detail', async () => ({
    template: `{% block sw_product_detail_content %}...{% endblock %}`,
}));
```

For async function configs, the block index is populated inside `configResolveMethod` when it is
awaited by `initComponent()`. Shopware's boot sequence awaits all registered component configs
before Vue mounts any component tree, so async overrides are always indexed before the first
`<sw-block name="...">` executes.

**Failure mode if this invariant is violated:** If `app.mount()` runs before all async configs
have been awaited (e.g. a plugin registers a lazy override outside the normal Shopware boot flow),
any `<sw-block>` that mounts will query an empty registry. The async override's Twig blocks are
never indexed, `hasBlockEntries()` returns `false`, no shim slots are created, and the default
block content renders unchanged. There is no warning because `sw-block` has no concept of
"expected overrides" — it only reads what is currently in the index.

This boot-order dependency is enforced by convention, not by the code. If the application boot
sequence is ever restructured, this must be re-validated.

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
