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
When migrating a component template, a core developer only replaces `{% block foo %}...{% endblock %}` with `<sw-block name="foo">...</sw-block>`. Nothing else: a block without `:data` uses the data scope of the component that declares it. The adapter detects the legacy override automatically.

**2. Zero touch for plugin developers**
Existing `Shopware.Component.override()` calls with Twig block templates continue to work. A `console.warn` tells the developer what to migrate and to which native syntax.

**3. Factory-independent**
The adapter hooks into `<sw-block>` itself, not the component factory. This means it works whether the parent component is registered through `Shopware.Component.register()` or is a pure Vue SFC — the `<sw-block>` tag is always present in the template and always mounts.

**4. Minimal overhead at render time**
The block index is built at override registration time (during boot), before any Vue component mounts. At render time, `<sw-block>` reads its layers from the index instead of reparsing Twig templates, and each reconstructed template is compiled once. A block without Twig or native layers renders its default content directly.

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

The inner content of any `{% block %}` is therefore already valid Vue template HTML. The adapter reconstructs it from the token tree and compiles it once with Vue's runtime compiler (`compile` from `vue`).

---

## Architecture

```
Boot time
─────────────────────────────────────────────────────────────────────
Shopware.Component.override('sw-product-detail', { template })   or   Shopware.Component.extend(name, parent, { template })
    │
    ├─ TemplateFactory.registerTemplateOverride() / extendComponentTemplate()   (unchanged)
    │
    └─ indexTwigBlocksFromTemplate(componentName, template, { priority, scoped })
            parse the TwigJS token tree, reconstruct each top-level {% block %}
            {% parent %} → <sw-block-parent />
            → one TwigBlockRecord per block, grouped per component

Runtime (every render of <sw-block name="sw_product_detail_content">)
─────────────────────────────────────────────────────────────────────
getBlockLayers(name, host)
    │  Twig layers from the index (the condition rewrite of a group runs once, on first read)
    │  + native <sw-block extends> layers, ordered by one rule (see 04)
    ▼
render default content, then every layer in order, in one pass
    │  a Twig layer runs its compiled render function with the host as rendering instance
    ▼
<SwBlockLayers> renders the top layer; <sw-block-parent /> renders the layer below it
```

---

## Implementation

### 1. Block index — `src/core/factory/twig-block-index.ts`

`indexTwigBlocksFromTemplate(componentName, rawTemplate, { priority, scoped })` parses a template with TwigJS
and stores one `TwigBlockRecord` per top-level `{% block %}`: block name, component name, priority, scope,
registration order, the raw reconstructed template and the rewritten `BlockEntry`. Consecutive templates of the
same component form a group, because a `v-if` chain can continue from one of their blocks into the next.

A group is transformed (see §5) the first time one of its blocks is read after it changed. Reads happen at render
time, so the continuation aliases of the host templates, which `template.factory.js` records when it resolves
them, are known by then. Adding an override only transforms its own group; a new alias re-transforms the groups
from the first affected one onward, because their case offsets depend on the groups before them.

Malformed templates are skipped with a warning.

### 2. Template reconstruction — `src/core/factory/reconstruct-twig-template.ts`

Walks the TwigJS token tree without running the TwigJS renderer. Raw tokens are kept verbatim, `{% parent %}`
becomes `<sw-block-parent />`, a nested `{% block %}` becomes a `<sw-block name>` with the reconstructed content.
Every other tag is dropped (see Known Limitations).

### 3. Twig layers — `src/app/component/structure/sw-block-override/shim/twig-shim-layer.ts`

A Twig layer compiles its template once and calls the render function with:

- the **host** (the component whose template declares the `<sw-block name>`) as current rendering instance, via
  `withCtx(render, host)`. Template refs land in the host's `$refs`, scope ids and component resolution are the
  host's, exactly as if the content were still part of the host template;
- a render context as `_ctx`. It reads and writes setup state of a native setup host through its data scope and
  everything else through the host proxy, so `@click="isOpen = true"`, `v-model`, `$emit`, `$slots` and `$t`
  behave as under TwigJS. Its `has` trap mirrors Vue's proxy for runtime-compiled templates, which run inside
  `with (_ctx)`.

There is no shim component: the layer's vnodes are part of the block's own render, which keeps DOM nodes (and
input focus) stable across updates. The first render of a Twig layer for a block name logs the deprecation
warning.

### 4. Hooks in `async-component.factory.ts`

`override()` and `extend()` call `indexTwigBlocks()`. Object configs are indexed right away, function configs
when they resolve; `initComponent()` awaits every config before Vue mounts anything (see Known Limitations).
Override templates get their override index as priority. Extend templates are `scoped`: their blocks only apply
to hosts that are, or extend, the extending component, so a child's block never leaks into its parent.

### 5. Conditional chains across layers — `transform-legacy-block-conditionals.ts` + `legacy-condition-context.ts`

Under TwigJS, blocks vanish from the merged template, so a `v-else` in one block continues a `v-if` in the block
before it or in the parent content of the block. Native blocks and Twig layers render those cases in separate
render functions, where Vue cannot link them. The adapter keeps that behaviour:

```html
<sw-block name="demo_block">
    <div v-if="showCore">Core case</div>
</sw-block>
```

```js
Shopware.Component.override('sw-demo', {
    template: `
{% block demo_block %}
    {% parent %}
    <div v-else>Legacy fallback</div>
{% endblock %}
`,
});
```

**Rewrite.** Both sides are rewritten to plain `v-if`s that call `$swLegacyBlock*` helpers:

```html
<div v-if="$swLegacyBlockIf('demo_block:0', showCore, { segmentCaseIndex: 0, isStartingCondition: true, renderOrderSegment: 'defaultSlot' })">Core case</div>
<div v-if="$swLegacyBlockElse('demo_block:0', { segmentCaseIndex: 0, isStartingCondition: false, renderOrderSegment: 'shimExtension' })">Legacy fallback</div>
```

- Templates are parsed with `parse` from `@vue/compiler-dom` (the module Vue's runtime compiler already bundles)
  and each directive is replaced at its exact source offset; nothing else in the template changes.
- Only the top-level elements of each block count. A chain that does not start with `v-if` continues the last
  started chain in render order and takes its key `<block of the starting case>:<chain index>`.
- Chains at the edge of a block are always rewritten, because a layer the template does not know about may
  continue them; chains in the middle of a block that nothing continues stay native.
- The Twig-rendered templates of native blocks are rewritten by `template.factory.js`. When a chain continues
  into another named block there, an alias `<block>:<index> → <chain key>` is recorded per component, so the Twig
  overrides of that block use the same key.

**Evaluation.** A case is addressed by its chain key, its render-order segment (`defaultSlot`, `shimExtension`,
`nativeExtension`) and its index within that segment. Chain state lives per host instance in a `WeakMap`, so it
disappears with the host. Because `<sw-block>` renders every layer in one pass and in order, an `else-if` /
`else` finds the results of all earlier cases of the same render; it renders only if none of them, back to the
case that started the chain, matched. A case whose block no longer renders it is dropped with the next render
of that block. A block that reads a case written by another block (a chain across two named blocks) re-renders
when that block renders a different result.

**Native setup hosts.** `.vue` templates are not rewritten. When a Twig layer directly above the default
content continues a chain, the chain start is taken from the rendered default content: a trailing `v-if`
placeholder comment means no case matched. The helpers are global properties resolved through the host, so
they work on any host.

---

## File Overview

| File | Purpose |
|------|---------|
| `core/factory/twig-block-index.ts` | Twig block records per block name, lazily transformed per component group |
| `core/factory/reconstruct-twig-template.ts` | TwigJS token tree → Vue template string |
| `core/factory/transform-legacy-block-conditionals.ts` | Cross-layer `v-if` chain rewrite on the Vue compiler AST |
| `app/composables/use-block-context.ts` | Layer registry and the ordering rule |
| `sw-block-override/shim/twig-shim-layer.ts` | Renders a Twig block in the host context |
| `sw-block-override/shim/legacy-condition-context.ts` | Chain evaluation and the `$swLegacyBlock*` helpers |
| `core/factory/async-component.factory.ts` | Indexes the templates of `override()` and `extend()` |

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

### Twig blocks with the same name in different components

Twig layers from `Component.override()` are keyed by block name only: an override of block `x` of component `A`
also renders in every other component that declares a native `<sw-block name="x">`. This matches the native
`<sw-block extends>` semantics but not TwigJS, where an override only affects its component and the components
extending it. `Component.extend()` layers are already scoped to their component.

### Vue component references inside Twig overrides (e.g. `<sw-card>`)

Resolved like in the host template: the host's local components, then the global component registry.

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
never indexed, the block has no Twig layers, and the default
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
