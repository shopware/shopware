# Issue 04: Stabilize sw-block / sw-block-parent Components

**Phase:** 1 — Foundation | **Priority:** Critical | **Estimate:** 2 weeks
**Labels:** `migration`, `infrastructure`, `templates`, `api-stabilization`

---

## Summary

Promote `<sw-block>` and `<sw-block-parent />` from experimental to production-ready. These replace Twig's `{% block %}` / `{% parent %}` and are the foundation for all template extensibility.

---

## Acceptance Criteria

- [ ] `<sw-block name="...">` defines an extensible block region
- [ ] `<sw-block extends="...">` overrides named block content
- [ ] `<sw-block-parent />` renders original/previous override's content
- [ ] Multi-plugin overrides applied in deterministic order (registration order)
- [ ] Data scoping works via `:data="$dataScope"` or equivalent
- [ ] `v-if`/`v-else` chains work with blocks (companion ESLint rule: `move-v-if-conditions-to-blocks.js`)
- [ ] Slot composition works with blocks (companion ESLint rule: `move-slots-to-wrap-blocks.js`)
- [ ] Nested blocks, empty blocks work correctly
- [ ] Minimal rendering overhead per block (~5,000 blocks across admin)
- [ ] TypeScript types correct

---

## Review Checklist

1. Does `sw-block-parent` always render correct previous content in multi-override chains?
2. Do block contents react to parent component data changes?
3. What is rendering overhead per `<sw-block>`?
4. Do blocks show meaningful names in Vue DevTools?

**Key files:** `sw-block-override/`, `use-block-context.ts`, ESLint rules in `core-rules/`

---

## Known Issues

- **v-if/v-else**: `<sw-block>` is a real component, not transparent. Breaks `v-if`/`v-else` sibling chains. ESLint rule mitigates at author-time.
- **Slot composition**: `<sw-block>` between parent and `<template v-slot>` breaks slot forwarding. ESLint rule addresses this.

---

## Done When

- Components promoted from experimental to stable
- All edge cases tested and handled
- Performance benchmarks show acceptable overhead
- ESLint companion rules validated
