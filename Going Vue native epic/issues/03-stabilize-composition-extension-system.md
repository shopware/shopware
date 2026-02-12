# Issue 03: Stabilize Composition Extension System

**Phase:** 1 — Foundation
**Priority:** Critical
**Estimate:** 2 weeks
**Labels:** `migration`, `infrastructure`, `composition-api`, `api-stabilization`

---

## Summary

Stabilize `createExtendableSetup()` and `overrideComponentSetup()` APIs in the composition extension system, promoting them from experimental to production-ready. These are the core APIs that every migrated component and every plugin override will depend on.

---

## Problem

The Composition API extension system (`composition-extension-system.ts`) is currently implemented and functional but marked as experimental. Before any core components are migrated in production, these APIs must be:

- Thoroughly reviewed for correctness and edge cases
- Hardened for production usage
- Documented with stable API contracts
- Tested with realistic plugin override scenarios

---

## Acceptance Criteria

- [ ] `createExtendableSetup()` API is reviewed, finalized, and documented
- [ ] `overrideComponentSetup()` API is reviewed, finalized, and documented
- [ ] Public API surface contract is defined: what is returned from `createExtendableSetup()` is the stable extension interface for plugin developers
- [ ] `previousState` object in `overrideComponentSetup()` correctly exposes all public refs, computed, and functions
- [ ] Override chains work correctly: Component → Override A → Override B → Override C
- [ ] Override ordering is deterministic and respectable (plugin load order)
- [ ] Ref unwrapping works correctly in templates (auto-unwrap for `ref()`, no `.value` needed in template context)
- [ ] TypeScript types are correct and exported for plugin developers
- [ ] Feature flag `ADMIN_COMPOSITION_API_EXTENSION_SYSTEM` behavior is reviewed — determine if this should remain or be removed for 6.8.0.0
- [ ] No memory leaks in override registration/cleanup
- [ ] API is exported through `Shopware.Component` namespace for discoverability

---

## Technical Approach

### Key APIs to Stabilize

```typescript
// Component author API
createExtendableSetup<PROPS, PUBLIC_API>({
  name: string,
  props: PROPS,
  setup: (props: PROPS) => { public: PUBLIC_API, private: PRIVATE_API }
})

// Plugin developer API
overrideComponentSetup<PUBLIC_API>(
  componentName: string,
  override: (previousState: PUBLIC_API) => Partial<PUBLIC_API>
)
```

### Review Checklist

1. **API ergonomics**: Is the `public` / `private` split intuitive? Should it be renamed?
2. **Type inference**: Does TypeScript correctly infer `previousState` types from the component definition?
3. **Reactive consistency**: Are all returned values properly reactive? Are computed refs read-only where appropriate?
4. **Error handling**: What happens when an override references a `previousState` property that doesn't exist?
5. **Lifecycle hooks**: Can overrides hook into `onMounted`, `onUnmounted`, etc.?
6. **Provide/inject**: Can overrides access injected values?

### Key File References

| File | Relevance |
|------|-----------|
| `src/app/adapter/composition-extension-system.ts` | Primary implementation |
| `src/core/factory/async-component.factory.ts` | Integration point — where overrides are registered |

---

## Testing Requirements

- [ ] Unit tests for `createExtendableSetup()` with various return shapes
- [ ] Unit tests for `overrideComponentSetup()` with single and multi-level overrides
- [ ] Unit test: Override adds new public properties
- [ ] Unit test: Override replaces an existing function
- [ ] Unit test: Override wraps an existing function (calling previousState)
- [ ] Unit test: Override chain with 3+ levels
- [ ] Unit test: TypeScript type tests (compile-time correctness)
- [ ] Integration test: Full component render with composition override
- [ ] Integration test: Override with lifecycle hooks
- [ ] Memory leak test: Register/unregister overrides repeatedly

---

## Definition of Done

- APIs are promoted from experimental to stable
- TypeScript types are finalized and exported
- API documentation is written with examples for plugin developers
- At least 2 core team members have reviewed the API design
- All tests pass
