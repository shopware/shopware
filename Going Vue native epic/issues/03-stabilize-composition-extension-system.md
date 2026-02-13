# Issue 03: Stabilize Composition Extension System

**Phase:** 1 — Foundation | **Priority:** Critical | **Estimate:** 2 weeks
**Labels:** `migration`, `infrastructure`, `composition-api`, `api-stabilization`

---

## Summary

Promote `createExtendableSetup()` and `overrideComponentSetup()` from experimental to production-ready. These are the core APIs every migrated component and plugin override will depend on.

---

## Acceptance Criteria

- [ ] Both APIs are reviewed, finalized, and documented
- [ ] Public API surface contract defined: return from `createExtendableSetup()` = stable extension interface
- [ ] `previousState` correctly exposes all public refs, computed, and functions
- [ ] Override chains work correctly and deterministically (respects plugin load order)
- [ ] Ref unwrapping works in templates (no `.value` needed)
- [ ] TypeScript types are correct and exported
- [ ] Feature flag `ADMIN_COMPOSITION_API_EXTENSION_SYSTEM` reviewed — keep or remove for 6.8.0.0
- [ ] No memory leaks in override registration/cleanup
- [ ] Exported through `Shopware.Component` namespace

---

## Review Checklist

1. Is the `public` / `private` split intuitive?
2. Does TypeScript correctly infer `previousState` types?
3. Are computed refs read-only where appropriate?
4. What happens when override references a non-existent `previousState` property?
5. Can overrides use lifecycle hooks (`onMounted`, etc.)?
6. Can overrides access `inject()`ed values?

**Key files:** `composition-extension-system.ts`, `async-component.factory.ts`

---

## Done When

- APIs promoted from experimental to stable
- TypeScript types finalized and exported
- API docs written with plugin developer examples
- 2+ core team members reviewed API design
