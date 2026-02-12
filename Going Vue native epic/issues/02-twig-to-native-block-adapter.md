# Issue 02: Build Twig → Native Block Runtime Adapter

**Phase:** 1 — Foundation
**Priority:** High
**Estimate:** 3–4 weeks
**Labels:** `migration`, `infrastructure`, `breaking-change-prevention`, `twig`, `templates`

---

## Summary

Build a runtime adapter that allows existing Twig-based plugin template overrides (`{% block %}` / `{% parent %}`) to continue working when a core component has been migrated to native Vue blocks (`<sw-block>` / `<sw-block-parent />`). This adapter bridges the two template systems during the transition period.

---

## Problem

When a core component migrates its template from `.html.twig` with `{% block %}` definitions to a native Vue template with `<sw-block name="...">`, existing plugin template overrides written in Twig syntax will no longer be resolved. The current `template.factory.js` uses Twig.js to merge template blocks at runtime — this pipeline has no knowledge of `<sw-block>` components.

Plugin templates like this will stop working:

```twig
{% block sw_product_detail_content %}
    {% parent %}
    <my-custom-card />
{% endblock %}
```

The adapter must translate these into the equivalent native block override at runtime:

```html
<sw-block extends="sw_product_detail_content">
    <sw-block-parent />
    <my-custom-card />
</sw-block>
```

---

## Acceptance Criteria

- [ ] When a core component uses native `<sw-block>` and a plugin registers a Twig template override for that component, the override is applied correctly at runtime
- [ ] `{% parent %}` in Twig overrides is mapped to `<sw-block-parent />` rendering behavior
- [ ] `{% block name %}...{% endblock %}` is mapped to `<sw-block extends="name">...</sw-block>`
- [ ] Multiple plugin overrides on the same block resolve in the correct priority order
- [ ] Component data context is properly available in the translated override content
- [ ] The adapter is only loaded/activated when legacy Twig overrides are detected (no perf impact when not needed)
- [ ] Deprecation warning is logged when the adapter is activated
- [ ] Adapter works for both `Component.override()` template overrides and standalone template registrations

---

## Technical Approach

### Location

Primary implementation in or alongside:
- `src/core/factory/template.factory.js`
- `src/app/composables/use-block-context.ts`

### Implementation Steps

1. **Detection**: During component registration/build, check if there are Twig template overrides registered for a component that now uses native blocks
2. **Parse Twig overrides**: Extract block names and inner content from the Twig override template
3. **Transform to block overrides**: For each `{% block name %}` in the plugin override, dynamically create an `sw-block extends="name"` component
4. **Handle `{% parent %}`**: Map `{% parent %}` to rendering the `<sw-block-parent />` slot in the correct position
5. **Register block overrides**: Inject the transformed overrides into the native block context system (`use-block-context.ts`)
6. **Lazy-load Twig.js**: Only load the Twig.js parser when legacy overrides are actually present, to avoid bundle size impact for shops without legacy plugins

### Limitations to Document

- Complex Twig logic (conditionals, loops within block overrides) may not translate cleanly
- Twig filters and functions used in override templates will not be available
- Performance overhead when running both template systems

### Key File References

| File | Relevance |
|------|-----------|
| `src/core/factory/template.factory.js` | Current Twig.js template resolution, block inheritance |
| `src/app/composables/use-block-context.ts` | Native block context (add/remove/get blocks) |
| `src/app/component/structure/sw-block-override/` | `sw-block` and `sw-block-parent` component implementations |
| `scripts/codemods/twig-block-removal/index.ts` | Existing Twig→block codemod (reference for transformation logic) |

---

## Testing Requirements

- [ ] Unit test: Single Twig block override on a native-block component
- [ ] Unit test: Multiple Twig block overrides from different plugins
- [ ] Unit test: `{% parent %}` rendering in translated overrides
- [ ] Unit test: Override priority/ordering is preserved
- [ ] Integration test: Full component render with mixed native + Twig-adapted overrides
- [ ] Performance test: Measure adapter overhead vs. pure native blocks
- [ ] Edge case: Empty block override
- [ ] Edge case: Nested block overrides

---

## Risks & Open Questions

- **Twig features beyond blocks**: If plugin overrides use Twig-specific features (filters, macros, includes) inside blocks, these cannot be translated. Need to assess how common this is in the plugin ecosystem.
- **Data scoping differences**: In Twig blocks, the component's `this` context is implicitly available. In `sw-block`, data is explicitly passed via `:data="$dataScope"`. The adapter must bridge this scoping difference.
- **Bundle size**: Even with lazy-loading, keeping Twig.js available adds to the potential bundle. Need to measure the lazy-loaded chunk size.
