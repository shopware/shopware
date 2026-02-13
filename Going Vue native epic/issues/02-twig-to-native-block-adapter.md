# Issue 02: Twig → Native Block Runtime Adapter

**Phase:** 1 — Foundation | **Priority:** High | **Estimate:** 3-4 weeks
**Labels:** `migration`, `infrastructure`, `breaking-change-prevention`, `twig`, `templates`

---

## Summary

Build a runtime adapter that allows existing Twig-based plugin template overrides (`{% block %}` / `{% parent %}`) to keep working when core components have migrated to native Vue blocks (`<sw-block>` / `<sw-block-parent />`).

---

## Acceptance Criteria

- [ ] Twig template overrides apply correctly to native `<sw-block>` components
- [ ] `{% parent %}` maps to `<sw-block-parent />` behavior
- [ ] `{% block name %}` maps to `<sw-block extends="name">`
- [ ] Multiple plugin overrides on the same block resolve in correct priority order
- [ ] Component data context available in translated override content
- [ ] Adapter only loaded when legacy Twig overrides exist (no perf impact otherwise)
- [ ] Deprecation warning logged when adapter activates

---

## Technical Approach

**Location:** `template.factory.js`, `use-block-context.ts`

1. **Detect**: Check for Twig overrides registered against native-block components
2. **Parse**: Extract block names and content from Twig override templates
3. **Transform**: Create `sw-block extends="name"` components dynamically, map `{% parent %}` to `<sw-block-parent />`
4. **Register**: Inject transformed overrides into native block context system
5. **Lazy-load Twig.js**: Only when legacy overrides are present

### Limitations

- Complex Twig logic (conditionals, loops in overrides) may not translate cleanly
- Twig filters/functions in overrides won't be available
- Performance overhead when running both template systems

---

## Risks

- **Twig features beyond blocks**: Filters, macros, includes inside blocks cannot be translated. Need to assess prevalence.
- **Data scoping**: Twig implicitly provides `this` context; `sw-block` uses explicit `:data="$dataScope"`. Adapter must bridge this.
- **Bundle size**: Even lazy-loaded, keeping Twig.js available adds to potential bundle.
