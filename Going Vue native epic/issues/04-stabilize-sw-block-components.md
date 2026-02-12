# Issue 04: Stabilize sw-block / sw-block-parent Components

**Phase:** 1 — Foundation
**Priority:** Critical
**Estimate:** 2 weeks
**Labels:** `migration`, `infrastructure`, `templates`, `api-stabilization`

---

## Summary

Stabilize the `<sw-block>` and `<sw-block-parent />` components, promoting them from experimental to production-ready. These components replace Twig's `{% block %}` / `{% parent %}` system and are the foundation for all template extensibility in the new architecture.

---

## Problem

The `sw-block` and `sw-block-parent` components exist in `src/app/component/structure/sw-block-override/` and are implemented but have no production usage yet. Before core templates are migrated, these components must handle all edge cases that Twig blocks currently handle, including:

- Block override inheritance chains
- Data scoping and context passing
- Interaction with `v-if`/`v-else` conditional rendering
- Interaction with `<template v-slot>` slot composition
- Multiple overrides targeting the same block from different plugins

---

## Acceptance Criteria

- [ ] `<sw-block name="block_name">` correctly defines an extensible block region
- [ ] `<sw-block extends="block_name">` correctly overrides the content of a named block
- [ ] `<sw-block-parent />` renders the original (or previous override's) block content at the insertion point
- [ ] Block overrides from multiple plugins are applied in deterministic order (plugin registration order)
- [ ] Data scoping works correctly: overriding blocks have access to the component's reactive data via `:data="$dataScope"` or equivalent
- [ ] `v-if`/`v-else` chains work correctly when blocks are involved (the `move-v-if-conditions-to-blocks.js` ESLint rule addresses this at authoring time)
- [ ] Slot composition works correctly when blocks wrap `<template v-slot>` (the `move-slots-to-wrap-blocks.js` ESLint rule addresses this at authoring time)
- [ ] Nested blocks (block inside block) work correctly
- [ ] Empty blocks (no content) render nothing and don't break layout
- [ ] Component is performant — rendering overhead per block is minimal
- [ ] TypeScript types are correct for the component props

---

## Technical Approach

### Components to Stabilize

1. **`sw-block`**: Renders its default slot content, or the override content if an override is registered for its `name`
2. **`sw-block-parent`**: When used inside a block override, renders the previous content (original or previous override)
3. **`use-block-context.ts` composable**: Manages the block registry — adding, removing, and retrieving block overrides

### Review Checklist

1. **Rendering correctness**: Does `sw-block-parent` always render the correct previous content in a multi-override chain?
2. **Reactivity**: Do block contents react to data changes in the parent component?
3. **Performance**: What is the rendering overhead per `<sw-block>`? With ~5,000 blocks across the admin, this matters.
4. **SSR compatibility**: Not required for admin, but should not use patterns that would break SSR.
5. **DevTools**: Do blocks show meaningful names in Vue DevTools for debugging?

### Known Issues to Address

- **v-if/v-else interaction**: `<sw-block>` is a real Vue component, not a transparent wrapper. Inserting it into a `v-if`/`v-else` chain breaks Vue's conditional rendering because Vue requires siblings for `v-if`/`v-else`. The ESLint rule `move-v-if-conditions-to-blocks.js` mitigates this at author-time, but runtime behavior should be validated.
- **Slot composition**: `<sw-block>` between a parent component and a `<template v-slot>` breaks slot forwarding. The ESLint rule `move-slots-to-wrap-blocks.js` addresses this, but edge cases should be tested.

### Key File References

| File | Relevance |
|------|-----------|
| `src/app/component/structure/sw-block-override/` | `sw-block` and `sw-block-parent` implementations |
| `src/app/composables/use-block-context.ts` | Block context registry |
| `eslint-rules/core-rules/move-v-if-conditions-to-blocks.js` | v-if handling ESLint rule |
| `eslint-rules/core-rules/move-slots-to-wrap-blocks.js` | Slot handling ESLint rule |

---

## Testing Requirements

- [ ] Unit test: Basic block rendering with default content
- [ ] Unit test: Block override replaces default content
- [ ] Unit test: `sw-block-parent` renders original content inside override
- [ ] Unit test: Multi-level override chain (Core → Plugin A → Plugin B)
- [ ] Unit test: Nested blocks (block within block)
- [ ] Unit test: Data reactivity — block content updates when component data changes
- [ ] Unit test: Empty block rendering
- [ ] Integration test: Block with `v-if` conditions
- [ ] Integration test: Block with slot composition
- [ ] Performance test: Render time with 100+ blocks on a single page

---

## Definition of Done

- Components are promoted from experimental to stable
- All edge cases documented above are tested and handled
- API documentation with usage examples is written
- Performance benchmarks show acceptable overhead per block
- ESLint companion rules are validated against stabilized behavior
