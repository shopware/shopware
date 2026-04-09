# Missing: `<sw-block>` and `<sw-block-parent>` Components Don't Exist

**Status:** Blocker — migrated components that use Twig blocks will not render until these are built.

---

## What the codemod produces

`transform-template.ts` converts every Twig block into custom Vue components:

```twig
{% block sw_card_header %}
  <h3>{{ title }}</h3>
{% endblock %}
```

becomes:

```html
<sw-block name="sw_card_header" :data="$dataScope">
  <h3>{{ title }}</h3>
</sw-block>
```

And `{{ parent() }}` becomes:

```html
<sw-block-parent/>
```

These two custom components — `<sw-block>` and `<sw-block-parent>` — need to be globally registered Vue components that implement Shopware's block override mechanism.

---

## What needs to be built

### `<sw-block>`

**Props:**
- `name: string` — the block identifier (used by the override system to look up registered overrides)
- `data: object` — the data scope passed down to child slots / overrides (`$dataScope`)

**Behavior:**
- By default, renders its default slot content
- If an override has been registered for `name`, renders the override instead (or wraps the default slot)
- Must be compatible with the existing `overrideComponentSetup` / block override mechanism

### `<sw-block-parent>`

**Props:** none (or implicit context from parent `<sw-block>`)

**Behavior:**
- Renders the "parent" (original) block content within an override
- Equivalent to `{{ parent() }}` in Twig: allows an overriding block to include the base content

### Global registration

Both components need to be globally registered so they are available in every migrated SFC without explicit imports:

```ts
// app.component('sw-block', SwBlock);
// app.component('sw-block-parent', SwBlockParent);
```

---

## Relationship to `$dataScope`

The `:data="$dataScope"` prop passed to every `<sw-block>` is described separately in [data-scope-binding.unfinished.md](data-scope-binding.unfinished.md). These two issues are closely coupled — resolving `$dataScope` is a prerequisite for `<sw-block>` to function correctly.

---

## Scope note

This task likely belongs to a **separate PR / companion task** that builds the runtime framework support. The codemod's output format is fixed — the template transform hardcodes the `<sw-block>` structure. Until these components exist, no migrated component that has Twig blocks will work at runtime.

---

## Acceptance check

- [ ] `<sw-block name="..." :data="...">` renders slot content by default
- [ ] `<sw-block>` respects registered block overrides
- [ ] `<sw-block-parent/>` renders original slot content within an override context
- [ ] Both components are globally registered in the administration app bootstrap
- [ ] Integration test: a migrated block-based component renders correctly in the browser
