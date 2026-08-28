# Twig Blocks in Shopware 6 Administration Vue Components

## Overview

The Shopware 6 Administration is a Vue 3 application with an unusual templating approach: **component templates are not written as standard Vue Single File Components (SFCs)** but as `.html.twig` files. This architecture exists specifically to enable extensibility through a **TwigJS block system**, allowing plugins to override or extend any section of any component's template without modifying core files.

---

## Why `.html.twig` Instead of `.vue` or `.html`?

Standard Vue SFCs (`.vue`) are compiled at build time. Their templates are sealed — a plugin cannot swap out parts of a pre-compiled component. To solve this, Shopware uses TwigJS at **runtime** to merge templates and their overrides, then passes the resulting HTML string to Vue as the component template.

The template file is imported as a raw string and assigned directly to the component config:

```typescript
// sw-tree-item/index.ts
import template from './sw-tree-item.html.twig';

export default {
    template,
    // ... component options
};
```

The TypeScript declaration that makes this import work:

```5:10:src/Administration/Resources/app/administration/src/html-shim.d.ts
declare module '*.html.twig' {
    const content: string;

    // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
    export default content;
}
```

---

## The Block System: How It Works

### Only `{% block %}` Is Supported — Not General Twig

This is the most critical constraint: **Twig output expressions (`{{ }}`) are intentionally disabled.** The TwigJS engine is configured to ignore its output token types before use. Data binding, computed values, and expressions are all handled by Vue. Twig is used solely as an **inheritance/block merging tool**.

From `template.factory.js`:

```36:52:src/Administration/Resources/app/administration/src/core/factory/template.factory.js
Twig.extend((TwigCore) => {
    /**
     * Remove tokens output_whitespace_pre, output_whitespace_post, output_whitespace_both and output.
     * These tokens are used for functions and data output.
     * Since the data binding is done in Vue this could lead to syntax issues.
     * We are only using the block system for template inheritance.
     *
     * @type {Array<any>}
     */
    TwigCore.token.definitions = TwigCore.token.definitions.filter((token) => {
        return (
            token.type !== TwigCore.token.type.output_whitespace_pre &&
            token.type !== TwigCore.token.type.output_whitespace_post &&
            token.type !== TwigCore.token.type.output_whitespace_both &&
            token.type !== TwigCore.token.type.output
        );
    });
```

### Supported Twig Syntax

| Syntax | Supported | Purpose |
|--------|-----------|---------|
| `{% block name %}...{% endblock %}` | ✅ | Define an overrideable section |
| `{% parent %}` | ✅ | Render the parent block's content |
| `{{ variable }}` | ❌ | Disabled — use Vue bindings instead |
| `{% if %}`, `{% for %}` | ❌ | Not for use — use `v-if`, `v-for` |
| `{% include %}`, `{% extends %}` | ❌ | Not for general use |

### Real-World Example: `sw-tree-item.html.twig`

A typical component template uses nested blocks to wrap each meaningful UI section:

```1:45:src/Administration/Resources/app/administration/src/app/component/tree/sw-tree-item/sw-tree-item.html.twig
{% block sw_tree_item %}
<div
    class="sw-tree-item"
    :class="styling"
    role="treeitem"
    :aria-label="getName(item)"
    :tabindex="active ? 0 : -1"
    :aria-current="active ? 'page' : undefined"
    :aria-expanded="isOpened ? 'true' : 'false'"
    :data-item-id="item.id"
    :aria-owns="item.id"
    :aria-selected="checked"
>
    {% block sw_tree_item_element %}
    <div
        v-droppable="{ dragGroup: 'sw-tree-item', data: item }"
        v-draggable="dragConf"
        class="sw-tree-item__element"
    >
        {% block sw_tree_item_element_leaf_icon %}
        <div
            v-if="item.childCount <= 0"
            class="sw-tree-item__leaf"
        ></div>
        {% endblock %}

        {% block sw_tree_item_element_toggle %}
        <div
            v-else
            class="sw-tree-item__toggle"
            role="button"
            tabindex="0"
            :aria-label="$t('sw-tree-item.toggleTreeItem', { name: getName(item) })"
            :aria-expanded="opened ? 'true' : 'false'"
            @click="openTreeItem(); getTreeItemChildren(item)"
            @keydown.enter="openTreeItem(); getTreeItemChildren(item)"
        >
            {% block sw_tree_item_element_toggle_icon %}
            <mt-icon
                size="24px"
                :name="opened ? 'regular-chevron-down-xxs' : 'regular-chevron-right-xxs'"
            />
            {% endblock %}
        </div>
        {% endblock %}
```

Notice that all dynamic binding (`v-if`, `:class`, `@click`) is done with standard Vue directives inside the blocks — Twig only controls **which sections exist and how they can be replaced**.

---

## How Plugins Override Blocks

### Registering a Component

```javascript
Shopware.Component.register('my-component', {
    template: `
{% block my_component_header %}
<h1>Original Header</h1>
{% endblock %}
`,
});
```

### Overriding a Specific Block

A plugin uses `Component.override()` and only redefines the blocks it wants to change. Any block not mentioned in the override keeps its original content.

```javascript
Shopware.Component.override('sw-product-detail', {
    template: `
{% block sw_product_detail_content_tabs %}
{% parent %}

<sw-card title="Custom Tab">
    <my-custom-component :product="product" />
</sw-card>
{% endblock %}
`,
});
```

- `{% parent %}` renders the original block content before (or after) the new content.
- Omitting `{% parent %}` fully replaces the block.

### Extending a Component (Creating a New One)

```javascript
Shopware.Component.extend('my-enhanced-field', 'sw-text-field', {
    template: `
{% block sw_field_input %}
<div class="my-wrapper">
    {% parent %}
</div>
{% endblock %}
`,
});
```

---

## Template Resolution Flow

The `TemplateFactory` (`template.factory.js`) drives the entire process:

```
1. Component imported         → registerComponentTemplate(name, rawTwigString)
2. Plugin override registered → registerTemplateOverride(name, rawTwigString, index)
3. Component built at runtime → resolveTemplates()
                                 └─ resolveTokens(): merges base + overrides block by block
                                 └─ mergeTokens(): handles {% parent %} substitution
4. Final HTML string          → passed to Vue as component.template
5. Vue compiles HTML          → reactive component rendered in browser
```

Multiple overrides can target the same component. The factory stores each override with its numeric `overrideIndex` and applies them in ascending index order.

---

## Block Naming Convention

Block names follow a flat, descriptive pattern based on the component and section:

```
sw_{component_name}_{section}_{subsection}
```

Examples from `sw-tree-item`:
- `sw_tree_item` — root block wrapping the entire component
- `sw_tree_item_element` — the main element container
- `sw_tree_item_element_toggle` — the expand/collapse toggle
- `sw_tree_item_element_toggle_icon` — just the icon inside the toggle

Every overrideable region gets its own named block, which is why templates tend to have many nested blocks.

---

## What You Cannot Do in Twig Templates

Since this is not a Twig application — it is a Vue application with Twig used only for block inheritance — the following patterns are invalid here even though they are valid Twig:

```twig
{# ❌ No output expressions #}
<p>{{ product.name }}</p>

{# ❌ No Twig control flow #}
{% if product.active %}...{% endif %}
{% for item in items %}...{% endfor %}

{# ❌ No Twig filters or functions #}
{{ price | number_format(2) }}

{# ❌ No Twig includes or extends #}
{% include 'some-template.twig' %}
```

All of the above must use Vue equivalents instead:

```html
<!-- ✅ Vue data binding -->
<p>{{ product.name }}</p>          <!-- Vue interpolation, works fine -->
<div v-if="product.active">...</div>
<div v-for="item in items" :key="item.id">...</div>
```

> **Note:** Vue interpolation (`{{ }}`) does work in the final rendered HTML — it is only the Twig `{{ }}` token that is ignored. After TwigJS resolves the blocks, the output HTML string is handed to Vue, which then processes its own `{{ }}` syntax normally.

---

## Current vs. Future State

| Feature | Current (≤6.7) | Transitional (6.8+) | Future |
|---------|---------------|---------------------|--------|
| Template files | `.html.twig` | `.html.twig` + SFCs | SFCs only |
| Block system | TwigJS `{% block %}` | Both TwigJS + `<sw-block>` | `<sw-block>` only |
| Parent content | `{% parent %}` | `{% parent %}` + `<sw-block-parent />` | `<sw-block-parent />` |
| Component API | Options API | Options API + Composition API | Composition API |

The **native block system** (`<sw-block name="...">` / `<sw-block-parent />`) is the future replacement, but **TwigJS blocks remain the stable, supported API** for plugin development today.

---

## Summary

- Administration Vue components use `.html.twig` files instead of SFCs so that block sections can be merged at runtime.
- TwigJS is intentionally restricted to `{% block %}` and `{% parent %}` — output expressions and control flow are disabled.
- Plugins override templates by calling `Shopware.Component.override()` with a template that only redefines the blocks they want to change.
- The `TemplateFactory` resolves all registered overrides into a final HTML string before Vue compiles it.
- All data binding, reactivity, and logic live in standard Vue syntax inside those blocks.
